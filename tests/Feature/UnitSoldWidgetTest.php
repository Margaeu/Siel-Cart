<?php

namespace Tests\Feature;

use App\Filament\Widgets\UnitSold;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Columns\Column;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Feature\Concerns\BuildsResolvableOrders;
use Tests\TestCase;

/**
 * The units sold widget sums order lines into one row per product or variant,
 * counting only orders that were completed and paid for. Rows come from the
 * snapshot each line took at checkout, so these cover what is counted, what is
 * kept apart, and that the history survives the catalogue changing under it.
 */
class UnitSoldWidgetTest extends TestCase
{
    use BuildsResolvableOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel('admin');
    }

    /**
     * The rows on the widget's current page in display order, each as
     * "Product Variant" => [units sold, sales amount].
     *
     * @return array<string, array{0: int, 1: string}>
     */
    private function rows(Testable $widget): array
    {
        return collect($widget->instance()->getTableRecords()->items())
            ->mapWithKeys(fn (OrderItem $row): array => [
                trim("{$row->product_name} {$row->variant_name}") => [$row->units_sold, $row->sales_amount],
            ])
            ->all();
    }

    /**
     * A simple lanyard at ₱50 and a shirt in two sizes at ₱350, with fixed
     * SKUs so a search for one cannot also match another.
     *
     * @return array{0: Product, 1: Product, 2: ProductVariant, 3: ProductVariant}
     */
    private function catalogue(): array
    {
        $lanyard = $this->product(['name' => 'CLSU Lanyard', 'sku' => 'LAN-1', 'price' => 50]);

        [$shirt, $medium, $large] = $this->shirtWithSizes(10, 10);
        $medium->update(['sku' => 'SHIRT-M']);
        $large->update(['sku' => 'SHIRT-L']);

        return [$lanyard, $shirt, $medium, $large];
    }

    public function test_only_completed_and_paid_orders_count_as_sales(): void
    {
        [$lanyard] = $this->catalogue();

        $this->line($this->completedOrder(), $lanyard, quantity: 2);

        // Each of these would add a lanyard to the total if it were counted.
        foreach ([
            ['status' => 'pending', 'payment_status' => 'pending', 'completed_at' => null],
            ['status' => 'processing', 'payment_status' => 'pending', 'completed_at' => null],
            ['status' => 'ready_for_pickup', 'payment_status' => 'paid', 'completed_at' => null],
            ['status' => 'cancelled', 'payment_status' => 'pending', 'completed_at' => null],
            ['status' => 'completed', 'payment_status' => 'pending'],
            ['status' => 'completed', 'payment_status' => 'refunded'],
        ] as $attributes) {
            $this->line($this->completedOrder($attributes), $lanyard);
        }

        $deleted = $this->completedOrder();
        $this->line($deleted, $lanyard);
        $deleted->delete();

        $widget = Livewire::test(UnitSold::class)->assertOk();

        $this->assertSame(['CLSU Lanyard' => [2, '100.00']], $this->rows($widget));
    }

    public function test_each_product_and_variant_is_its_own_row_summed_across_orders(): void
    {
        [$lanyard, $shirt, $medium, $large] = $this->catalogue();

        $first = $this->completedOrder();
        $this->line($first, $lanyard);
        $this->line($first, $shirt, $medium, 2);

        $second = $this->completedOrder();
        $this->line($second, $lanyard, quantity: 3);
        $this->line($second, $shirt, $large);

        $widget = Livewire::test(UnitSold::class)->assertOk();

        // Most units first until the admin sorts otherwise.
        $this->assertSame([
            'CLSU Lanyard' => [4, '200.00'],
            'CLSU Shirt Green / Medium' => [2, '700.00'],
            'CLSU Shirt Green / Large' => [1, '350.00'],
        ], $this->rows($widget));

        // The totals sort too, and here the money ranks them the other way.
        $widget->sortTable('sales_amount', 'desc');

        $this->assertSame([
            'CLSU Shirt Green / Medium' => [2, '700.00'],
            'CLSU Shirt Green / Large' => [1, '350.00'],
            'CLSU Lanyard' => [4, '200.00'],
        ], $this->rows($widget));
    }

    public function test_sales_stay_listed_from_their_snapshot_after_the_product_is_deleted(): void
    {
        [$lanyard, $shirt, $medium, $large] = $this->catalogue();

        $order = $this->completedOrder();
        $this->line($order, $lanyard, quantity: 3);
        $this->line($order, $shirt, $medium, 2);
        $this->line($order, $shirt, $large);

        $lanyard->forceDelete();
        $shirt->forceDelete();

        // Nothing but the snapshot is left to tell these lines apart.
        $this->assertSame(0, OrderItem::query()->whereNotNull('product_id')->count());
        $this->assertSame(0, OrderItem::query()->whereNotNull('product_variant_id')->count());

        $widget = Livewire::test(UnitSold::class)
            ->assertOk()
            ->assertSeeText('SHIRT-M')
            ->assertSeeText('SHIRT-L');

        $this->assertSame([
            'CLSU Lanyard' => [3, '150.00'],
            'CLSU Shirt Green / Medium' => [2, '700.00'],
            'CLSU Shirt Green / Large' => [1, '350.00'],
        ], $this->rows($widget));
    }

    public function test_refunds_and_exchanges_leave_the_totals_alone(): void
    {
        [$lanyard] = $this->catalogue();

        $line = $this->line($this->completedOrder(), $lanyard, quantity: 3);

        $this->refund($line, 50);
        $this->exchangeFaultyItem($line);

        $widget = Livewire::test(UnitSold::class)
            ->assertOk()
            ->assertDontSeeText('Refund')
            ->assertDontSeeText('Net Units');

        $this->assertSame(['CLSU Lanyard' => [3, '150.00']], $this->rows($widget));
    }

    public function test_the_date_filters_use_the_completion_date_and_include_both_boundary_days(): void
    {
        // Named for when they were collected. All were created today, so a
        // filter on created_at would find none of them.
        foreach ([
            'Before' => '2025-02-28 23:59:59',
            'First day' => '2025-03-01 00:00:00',
            'Last day' => '2025-03-31 23:59:59',
            'After' => '2025-04-01 00:00:00',
        ] as $name => $completedAt) {
            $this->line($this->completedOrder(['completed_at' => $completedAt]), $this->product(['name' => $name]));
        }

        $widget = Livewire::test(UnitSold::class)
            ->filterTable('completed_at', ['from' => '2025-03-01', 'until' => '2025-03-31']);

        $this->assertEqualsCanonicalizing(['First day', 'Last day'], array_keys($this->rows($widget)));

        $widget->filterTable('completed_at', ['from' => '2025-03-01', 'until' => null]);

        $this->assertEqualsCanonicalizing(['First day', 'Last day', 'After'], array_keys($this->rows($widget)));

        $widget->filterTable('completed_at', ['from' => null, 'until' => '2025-03-31']);

        $this->assertEqualsCanonicalizing(['Before', 'First day', 'Last day'], array_keys($this->rows($widget)));
    }

    public function test_search_matches_product_name_variant_name_and_sku(): void
    {
        [$lanyard, $shirt, $medium, $large] = $this->catalogue();

        $order = $this->completedOrder();
        $this->line($order, $lanyard, quantity: 3);
        $this->line($order, $shirt, $medium, 2);
        $this->line($order, $shirt, $large);

        $widget = Livewire::test(UnitSold::class)->searchTable('Lanyard');

        $this->assertSame(['CLSU Lanyard' => [3, '150.00']], $this->rows($widget));

        $widget->searchTable('Medium');

        $this->assertSame(['CLSU Shirt Green / Medium' => [2, '700.00']], $this->rows($widget));

        $widget->searchTable('SHIRT-L');

        $this->assertSame(['CLSU Shirt Green / Large' => [1, '350.00']], $this->rows($widget));
    }

    public function test_the_table_shows_the_four_sales_columns_and_nothing_to_act_on(): void
    {
        $order = $this->completedOrder();
        $this->line($order, $this->product(['name' => 'CLSU Tumbler', 'sku' => 'TUM-1', 'price' => 525]), quantity: 2);
        // A product sold by variant has no SKU of its own to snapshot.
        $this->line($order, $this->product(['name' => 'CLSU Tote', 'sku' => null]));

        $widget = Livewire::test(UnitSold::class)
            ->assertOk()
            ->assertSeeText('Units Sold')
            ->assertSeeText('Product / Variant')
            ->assertSeeText('Sales Amount')
            ->assertSeeText('TUM-1')
            ->assertSeeText('No SKU')
            ->assertSeeText('₱1,050.00');

        $table = $widget->instance()->getTable();

        $this->assertSame(
            ['product_name', 'product_sku', 'units_sold', 'sales_amount'],
            array_map(fn (Column $column): string => $column->getName(), array_values($table->getColumns())),
        );
        $this->assertSame([], $table->getRecordActions());
        $this->assertSame([], $table->getHeaderActions());
        $this->assertSame([], $table->getToolbarActions());
    }

    public function test_an_empty_table_explains_what_is_counted(): void
    {
        // Waiting at the office, so not a sale yet.
        $this->line(
            $this->completedOrder(['status' => 'ready_for_pickup', 'completed_at' => null]),
            $this->product(),
        );

        Livewire::test(UnitSold::class)
            ->assertOk()
            ->assertSeeText('No sales to show')
            ->assertSeeText('completed and paid');
    }
}
