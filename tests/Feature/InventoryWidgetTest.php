<?php

namespace Tests\Feature;

use App\Filament\Widgets\InventoryManagement;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The inventory widget lists one row per sellable thing rather than one row
 * per product: a simple product stands for itself, while a variable product is
 * replaced by its variants, each carrying its own SKU, stock and threshold.
 * Nothing else in the admin makes that distinction, so the row set, the status
 * rules derived from it and the ordering are all covered here.
 */
class InventoryWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel('admin');
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'is_active' => true,
            'has_variants' => false,
            'stock_quantity' => 50,
            'low_stock_threshold' => 10,
        ], $attributes));
    }

    private function variant(Product $product, array $attributes = []): ProductVariant
    {
        return ProductVariant::factory()->for($product)->create(array_merge([
            'is_active' => true,
            'stock_quantity' => 50,
            'low_stock_threshold' => 10,
        ], $attributes));
    }

    /**
     * The row a SKU produced. The table filters rows, the model query does
     * not, so this resolves rows the table is expected to hide as well.
     */
    private function row(string $sku): InventoryItem
    {
        return InventoryItem::query()->where('sku', $sku)->sole();
    }

    public function test_simple_products_and_active_variants_each_render_one_row(): void
    {
        $this->product([
            'name' => 'Campus Tumbler',
            'sku' => 'TUMBLER-1',
            'stock_quantity' => 40,
            'low_stock_threshold' => 5,
        ]);

        $shirt = $this->product([
            'name' => 'Campus Shirt',
            'sku' => 'SHIRT-PARENT',
            'has_variants' => true,
            'stock_quantity' => 999,
            'low_stock_threshold' => 1,
        ]);
        $this->variant($shirt, ['name' => 'Small', 'sku' => 'SHIRT-S', 'stock_quantity' => 12, 'low_stock_threshold' => 4]);
        $this->variant($shirt, ['name' => 'Large', 'sku' => 'SHIRT-L', 'stock_quantity' => 3, 'low_stock_threshold' => 4]);

        Livewire::test(InventoryManagement::class)
            ->assertOk()
            ->assertCanSeeTableRecords([
                $this->row('TUMBLER-1'),
                $this->row('SHIRT-S'),
                $this->row('SHIRT-L'),
            ])
            ->assertSee('Campus Tumbler')
            ->assertSee('Campus Shirt')
            ->assertSee('Small')
            ->assertSee('Large')
            // A variable product never stands as a row of its own, so neither
            // its SKU nor its combined stock reaches the table.
            ->assertDontSee('SHIRT-PARENT');

        $this->assertSame(3, $this->row('SHIRT-L')->stock_quantity);
        $this->assertSame(4, $this->row('SHIRT-L')->low_stock_threshold);
        $this->assertSame(0, InventoryItem::query()->where('stock_quantity', 999)->count());
    }

    #[DataProvider('stockScenarios')]
    public function test_status_follows_stock_against_the_threshold(int $stock, int $threshold, string $status): void
    {
        $this->product(['sku' => 'SIMPLE-1', 'stock_quantity' => $stock, 'low_stock_threshold' => $threshold]);

        $variable = $this->product(['has_variants' => true]);
        $this->variant($variable, ['sku' => 'VARIANT-1', 'stock_quantity' => $stock, 'low_stock_threshold' => $threshold]);

        $this->assertSame($status, $this->row('SIMPLE-1')->status);
        $this->assertSame($status, $this->row('VARIANT-1')->status);
    }

    public static function stockScenarios(): array
    {
        return [
            'empty shelf' => [0, 5, 'out_of_stock'],
            'stock down to the threshold' => [5, 5, 'low_stock'],
            'stock above the threshold' => [6, 5, 'in_stock'],
            'a threshold of zero disables low stock' => [3, 0, 'in_stock'],
            'a threshold of zero still reports an empty shelf' => [0, 0, 'out_of_stock'],
        ];
    }

    public function test_rows_are_ordered_out_of_stock_then_low_stock_then_in_stock(): void
    {
        // Named so that alphabetical order is the reverse of the expected
        // order -- otherwise the name tiebreaker could pass this on its own.
        $this->product(['name' => 'Aaa Plenty', 'sku' => 'IN-1', 'stock_quantity' => 50, 'low_stock_threshold' => 5]);
        $this->product(['name' => 'Bbb Running Out', 'sku' => 'LOW-1', 'stock_quantity' => 5, 'low_stock_threshold' => 5]);
        $this->product(['name' => 'Ccc Empty', 'sku' => 'OUT-1', 'stock_quantity' => 0, 'low_stock_threshold' => 5]);

        Livewire::test(InventoryManagement::class)
            ->assertCanSeeTableRecords([
                $this->row('OUT-1'),
                $this->row('LOW-1'),
                $this->row('IN-1'),
            ], inOrder: true);
    }

    public function test_inactive_products_and_variants_are_hidden_until_the_filter_is_cleared(): void
    {
        $this->product(['sku' => 'ACTIVE-1']);
        $this->product(['sku' => 'INACTIVE-1', 'is_active' => false]);

        $stocked = $this->product(['has_variants' => true]);
        $this->variant($stocked, ['sku' => 'VARIANT-ON']);
        $this->variant($stocked, ['sku' => 'VARIANT-OFF', 'is_active' => false]);

        // An active variant of an inactive product is not on sale either.
        $retired = $this->product(['has_variants' => true, 'is_active' => false]);
        $this->variant($retired, ['sku' => 'RETIRED-VARIANT']);

        Livewire::test(InventoryManagement::class)
            ->assertCanSeeTableRecords([$this->row('ACTIVE-1'), $this->row('VARIANT-ON')])
            ->assertCanNotSeeTableRecords([
                $this->row('INACTIVE-1'),
                $this->row('VARIANT-OFF'),
                $this->row('RETIRED-VARIANT'),
            ])
            ->removeTableFilter('is_active')
            ->assertCanSeeTableRecords([
                $this->row('INACTIVE-1'),
                $this->row('VARIANT-OFF'),
                $this->row('RETIRED-VARIANT'),
            ]);
    }

    public function test_soft_deleted_products_leave_no_rows_behind(): void
    {
        $simple = $this->product(['sku' => 'GONE-1']);

        $variable = $this->product(['has_variants' => true]);
        $this->variant($variable, ['sku' => 'GONE-VARIANT']);

        $simple->delete();
        $variable->delete();

        $this->assertSame(0, InventoryItem::query()->whereIn('sku', ['GONE-1', 'GONE-VARIANT'])->count());
    }

    public function test_the_status_filter_narrows_the_table(): void
    {
        $this->product(['sku' => 'IN-1', 'stock_quantity' => 50, 'low_stock_threshold' => 5]);
        $this->product(['sku' => 'LOW-1', 'stock_quantity' => 5, 'low_stock_threshold' => 5]);
        $this->product(['sku' => 'OUT-1', 'stock_quantity' => 0, 'low_stock_threshold' => 5]);

        Livewire::test(InventoryManagement::class)
            ->filterTable('status', ['low_stock'])
            ->assertCanSeeTableRecords([$this->row('LOW-1')])
            ->assertCanNotSeeTableRecords([$this->row('IN-1'), $this->row('OUT-1')])
            ->filterTable('status', ['low_stock', 'out_of_stock'])
            ->assertCanSeeTableRecords([$this->row('LOW-1'), $this->row('OUT-1')])
            ->assertCanNotSeeTableRecords([$this->row('IN-1')]);
    }

    public function test_search_matches_product_name_variant_name_and_sku(): void
    {
        $this->product(['name' => 'Lanyard', 'sku' => 'LANYARD-1']);

        $hoodie = $this->product(['name' => 'Hoodie', 'has_variants' => true]);
        $this->variant($hoodie, ['name' => 'Maroon', 'sku' => 'HOODIE-MAROON']);

        Livewire::test(InventoryManagement::class)
            ->searchTable('Lanyard')
            ->assertCanSeeTableRecords([$this->row('LANYARD-1')])
            ->assertCanNotSeeTableRecords([$this->row('HOODIE-MAROON')])
            ->searchTable('Maroon')
            ->assertCanSeeTableRecords([$this->row('HOODIE-MAROON')])
            ->assertCanNotSeeTableRecords([$this->row('LANYARD-1')])
            ->searchTable('Hoodie')
            ->assertCanSeeTableRecords([$this->row('HOODIE-MAROON')])
            ->searchTable('LANYARD-1')
            ->assertCanSeeTableRecords([$this->row('LANYARD-1')])
            ->assertCanNotSeeTableRecords([$this->row('HOODIE-MAROON')]);
    }

    public function test_out_of_stock_and_low_stock_rows_are_flagged_for_highlighting(): void
    {
        $this->product(['sku' => 'OUT-1', 'stock_quantity' => 0, 'low_stock_threshold' => 5]);
        $this->product(['sku' => 'LOW-1', 'stock_quantity' => 5, 'low_stock_threshold' => 5]);
        $this->product(['sku' => 'IN-1', 'stock_quantity' => 50, 'low_stock_threshold' => 5]);

        $widget = Livewire::test(InventoryManagement::class)
            // The class only highlights anything because the widget renders
            // its own view, which carries the rule for it.
            ->assertSeeHtml('.fi-ta-row.' . InventoryManagement::ALERT_ROW_CLASS)
            ->assertSeeHtml('fi-ta-row ' . InventoryManagement::ALERT_ROW_CLASS);

        $table = $widget->instance()->getTable();

        $this->assertContains(InventoryManagement::ALERT_ROW_CLASS, $table->getRecordClasses($this->row('OUT-1')));
        $this->assertContains(InventoryManagement::ALERT_ROW_CLASS, $table->getRecordClasses($this->row('LOW-1')));
        $this->assertNotContains(InventoryManagement::ALERT_ROW_CLASS, $table->getRecordClasses($this->row('IN-1')));
    }

    public function test_the_table_reads_stock_in_a_fixed_number_of_queries(): void
    {
        $this->seedInventory(1);
        $small = $this->countStockSelects();

        $this->seedInventory(6);
        $large = $this->countStockSelects();

        // Guards the comparison below against passing on zero queries, which
        // would mean the counter stopped matching the statements it counts.
        $this->assertGreaterThan(0, $small);
        $this->assertSame($small, $large);
    }

    private function seedInventory(int $groups): void
    {
        foreach (range(1, $groups) as $ignored) {
            $this->product();

            $variable = $this->product(['has_variants' => true]);
            $this->variant($variable);
            $this->variant($variable);
        }
    }

    /**
     * How many SELECTs against the stock tables one render of the widget costs.
     * A per-row lookup would make this grow with the number of rows.
     */
    private function countStockSelects(): int
    {
        $statements = [];

        DB::listen(function ($query) use (&$statements): void {
            $statements[] = $query->sql;
        });

        Livewire::test(InventoryManagement::class)->assertOk();

        return count(array_filter(
            $statements,
            fn (string $sql): bool => str_starts_with($sql, 'select') && str_contains($sql, 'product'),
        ));
    }
}
