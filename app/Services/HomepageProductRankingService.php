<?php

namespace App\Services;

use App\Enums\OrderItemResolutionType;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ranks in-stock products for the homepage's Best Sellers and Top Picks
 * sections, from a rolling 7-day window of completed, paid sales -- the same
 * definition of "sold" as the admin's Units Sold widget. Admin highlighting
 * plays no part: a product earns these sections by selling, not by being
 * flagged (see Product::eligibleForHomepage()).
 *
 * Stock never contributes to the ranking -- it only decides eligibility, via
 * Product::eligibleForHomepage(). Neither section shows a product without at
 * least one qualifying sale in the window, so only products that sold are
 * loaded at all. Within that set, both sections rank the same way: units
 * sold in the window (net of refunds), then
 * distinct completed orders, approved average rating, views, recency, and
 * product id, in that order, as deterministic tie-breakers.
 *
 * Recency here is the product's own created_at, not the sale's -- it sits
 * beside rating and views as a product-level tiebreaker the doc groups with
 * them, not a sales-level one.
 */
class HomepageProductRankingService
{
    private const WINDOW_DAYS = 7;

    private const BEST_SELLERS_PER_CATEGORY = 1;

    private const MAX_TOP_PICKS = 8;

    /**
     * Memoized for this instance, so bestSellers() and topPicks() in one
     * request rank one snapshot and pay for it once.
     */
    private ?Collection $ranked = null;

    public function __construct(private readonly HomepageRankingCache $cache) {}

    /**
     * One winner per category, with a qualifying sale in the
     * window. The global sales ranking determines each category's order.
     * A category with no qualifying sale gets no winner.
     */
    public function bestSellers(): Collection
    {
        return $this->rankedEligibleProducts()
            ->groupBy('category_id')
            ->flatMap(fn (Collection $products) => $products->take(self::BEST_SELLERS_PER_CATEGORY))
            ->values();
    }

    /**
     * The top eligible products store-wide, by completed sales in the
     * window. A product needs at least one qualifying sale to appear, the
     * same bar as Best Sellers: Top Picks used to pad itself out with
     * zero-sale highlighted products ordered by rating, views, and recency,
     * which put items nobody had bought under a heading that claims to be
     * the store's top sellers. Best Sellers and Top Picks are ranked
     * independently, so a product can win its category and also appear here.
     */
    public function topPicks(): Collection
    {
        return $this->rankedEligibleProducts()->take(self::MAX_TOP_PICKS);
    }

    /**
     * Every eligible product with a qualifying sale, ranked once and shared
     * by both sections so a render only pays for the sales query and the
     * tie-break sort a single time.
     *
     * Sales are read first and only the products that sold are loaded.
     * Without the highlight gate the eligible set is the whole in-stock
     * catalogue, and loading all of it (with stock and review aggregates) to
     * throw away everything that didn't sell would grow with the catalogue
     * instead of with the week's orders. A product whose units were all
     * refunded nets to zero and is dropped here too.
     *
     * The sales map is the only cached part (see HomepageRankingCache).
     * Eligibility -- active, in stock, active category -- and the card data
     * are read live on every call, so a product going out of stock or being
     * deactivated drops out immediately without invalidating anything.
     *
     * withCardData(): the ranked products are rendered as homepage cards, and
     * loading only `category` here left each Best Seller / Top Pick card to
     * lazy-load its image and variants on its own -- two queries per card.
     * Category names are loaded together for the Best Sellers headings.
     */
    private function rankedEligibleProducts(): Collection
    {
        if ($this->ranked !== null) {
            return $this->ranked;
        }

        $sales = collect($this->cache->salesMap(
            fn (): array => $this->salesSince(now()->subDays(self::WINDOW_DAYS)),
        ));

        if ($sales->isEmpty()) {
            return $this->ranked = collect();
        }

        $products = Product::query()
            ->eligibleForHomepage()
            ->whereKey($sales->keys()->all())
            ->withCardData()
            ->with('category:id,name')
            ->get();

        foreach ($products as $product) {
            $entry = $sales->get($product->id);
            $product->units_sold = $entry['units_sold'];
            $product->distinct_orders_count = $entry['distinct_orders_count'];
        }

        return $this->ranked = $products
            ->sortBy([
                ['units_sold', 'desc'],
                ['distinct_orders_count', 'desc'],
                ['average_rating', 'desc'],
                ['views_count', 'desc'],
                ['created_at', 'desc'],
                ['id', 'asc'],
            ])
            ->values();
    }

    /**
     * Net qualifying units sold and distinct completed orders per product,
     * from completed, paid orders whose completed_at falls in the window
     * (boundary included).
     *
     * "Completed and paid" is the same definition the admin's Units Sold
     * widget uses (see App\Filament\Widgets\UnitSold) -- status alone is not
     * enough, since payment_status is editable independently on the order
     * form and can drift from it. Keep both in sync with whatever counts as
     * a sale there.
     *
     * A refunded unit comes off units_sold -- the sale was undone. An
     * exchanged unit stays counted: the customer is still holding something
     * bought in the window, and only a refund settles a unit for good (see
     * OrderItem::resolvableQuantity()).
     *
     * Aggregated in SQL. This used to hydrate every qualifying order line
     * plus its refunds and sum them in PHP, so the cost grew with the week's
     * order volume in rows and memory. The semantics are unchanged:
     *  - the clamp is per line: max(0, quantity - refunded quantity), written
     *    as CASE WHEN because sqlite (the test connection) has no GREATEST();
     *  - distinct orders count every qualifying order with a line for the
     *    product, including a line whose units were all refunded;
     *  - soft-deleted orders are excluded, as whereHas('order') did through
     *    Order's SoftDeletes scope;
     *  - products whose net units come to zero are dropped.
     *
     * @return array<int, array{units_sold: int, distinct_orders_count: int}>
     */
    private function salesSince(CarbonInterface $since): array
    {
        $refunded = DB::table('return_refund_resolutions')
            ->selectRaw('COALESCE(SUM(return_refund_resolutions.quantity), 0)')
            ->whereColumn('return_refund_resolutions.order_item_id', 'order_items.id')
            ->where('return_refund_resolutions.type', OrderItemResolutionType::Refund->value);

        $lines = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotNull('order_items.product_id')
            ->whereNull('orders.deleted_at')
            ->where('orders.status', 'completed')
            ->where('orders.payment_status', 'paid')
            ->where('orders.completed_at', '>=', $since)
            ->select(['order_items.product_id', 'order_items.order_id'])
            ->selectSub($refunded, 'refunded_quantity')
            ->addSelect('order_items.quantity');

        $rows = DB::query()
            ->fromSub($lines, 'lines')
            ->select('lines.product_id')
            ->selectRaw('SUM(CASE WHEN lines.quantity > lines.refunded_quantity THEN lines.quantity - lines.refunded_quantity ELSE 0 END) AS units_sold')
            ->selectRaw('COUNT(DISTINCT lines.order_id) AS distinct_orders_count')
            ->groupBy('lines.product_id')
            ->get();

        $sales = [];

        foreach ($rows as $row) {
            // MySQL returns SUM/COUNT as strings, sqlite as integers.
            $units = (int) $row->units_sold;

            if ($units > 0) {
                $sales[(int) $row->product_id] = [
                    'units_sold' => $units,
                    'distinct_orders_count' => (int) $row->distinct_orders_count,
                ];
            }
        }

        return $sales;
    }
}
