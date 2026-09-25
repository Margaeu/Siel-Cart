<?php

namespace App\Services;

use App\Enums\OrderItemResolutionType;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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

    private const MAX_BEST_SELLERS = 8;

    private const MAX_TOP_PICKS = 8;

    private ?Collection $ranked = null;

    /**
     * At most one winner per category: the highest-ranked eligible product
     * with at least one qualifying sale in the window. A category with no
     * qualifying sale gets no winner, and a zero-sale product never wins one.
     */
    public function bestSellers(): Collection
    {
        return $this->rankedEligibleProducts()
            ->groupBy('category_id')
            ->map(fn (Collection $products) => $products->first())
            ->values()
            ->take(self::MAX_BEST_SELLERS);
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
     */
    private function rankedEligibleProducts(): Collection
    {
        if ($this->ranked !== null) {
            return $this->ranked;
        }

        $sales = $this->salesSince(now()->subDays(self::WINDOW_DAYS))
            ->filter(fn (array $entry) => $entry['units_sold'] > 0);

        if ($sales->isEmpty()) {
            return $this->ranked = collect();
        }

        $products = Product::query()
            ->eligibleForHomepage()
            ->whereKey($sales->keys()->all())
            ->with('category')
            ->withStockAggregates()
            ->withReviewAggregates()
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
     * @return Collection<int, array{units_sold: int, distinct_orders_count: int}>
     */
    private function salesSince(CarbonInterface $since): Collection
    {
        $items = OrderItem::query()
            ->select(['id', 'product_id', 'order_id', 'quantity'])
            ->whereNotNull('product_id')
            ->whereHas('order', fn (Builder $query) => $query
                ->where('status', 'completed')
                ->where('payment_status', 'paid')
                ->where('completed_at', '>=', $since))
            ->with(['resolutions' => fn ($query) => $query
                ->where('type', OrderItemResolutionType::Refund->value)])
            ->get();

        $byProduct = [];

        foreach ($items as $item) {
            $refunded = (int) $item->resolutions->sum('quantity');
            $net = max(0, $item->quantity - $refunded);

            $entry = $byProduct[$item->product_id] ??= ['units_sold' => 0, 'orders' => []];
            $entry['units_sold'] += $net;
            $entry['orders'][$item->order_id] = true;
            $byProduct[$item->product_id] = $entry;
        }

        return collect($byProduct)->map(fn (array $entry) => [
            'units_sold' => $entry['units_sold'],
            'distinct_orders_count' => count($entry['orders']),
        ]);
    }
}
