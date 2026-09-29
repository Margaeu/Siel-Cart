<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\Banners\Pages\ListBanners;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Filament\Resources\ReturnRefunds\Pages\ListReturnRefunds;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\Themes\Pages\ListThemes;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Widgets\InventoryManagement;
use App\Filament\Widgets\UnitSold;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Typing in an admin table's search box must never blow up. `Customer::name`
 * and `User::name` are accessors, so a column like `customer.name` that is
 * left on Filament's default search queries a `name` column that does not
 * exist, and the panel answers with "Error while loading page".
 *
 * MySQL throws on that unknown column, but sqlite (the test connection)
 * quietly reads a missing double-quoted "name" as a string literal and just
 * matches nothing. So the smoke test below only guards against syntax errors
 * and LIKE escaping; the bug itself is pinned by the tests that assert real
 * customers are actually found.
 */
class TableSearchTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        $admin = new User;
        $admin->forceFill([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'admin@example.com',
            'password' => 'password',
            'is_active' => true,
        ])->save();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        Gate::before(fn () => true);
        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);
    }

    private function order(Customer $customer, string $number): Order
    {
        return Order::create([
            'order_number' => $number,
            'customer_id' => $customer->id,
            'subtotal' => 350,
            'total' => 350,
            'pickup_location' => 'UBAP_office',
            'payment_method' => 'cash_on_pickup',
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);
    }

    public function test_orders_search_matches_customer_first_last_and_full_name_and_order_number(): void
    {
        $danna = Customer::factory()->create(['first_name' => 'Danna', 'last_name' => 'Cruz']);
        $other = Customer::factory()->create(['first_name' => 'Marco', 'last_name' => 'Reyes']);
        $dannaOrder = $this->order($danna, 'ORD-AAA111');
        $otherOrder = $this->order($other, 'ORD-BBB222');
        $this->actingAsAdmin();

        foreach (['danna', 'cruz', 'danna cruz', 'ORD-AAA'] as $term) {
            Livewire::test(ListOrders::class)
                ->searchTable($term)
                ->assertCanSeeTableRecords([$dannaOrder])
                ->assertCanNotSeeTableRecords([$otherOrder]);
        }
    }

    public function test_orders_search_still_finds_orders_of_a_deleted_customer(): void
    {
        $danna = Customer::factory()->create(['first_name' => 'Danna', 'last_name' => 'Cruz']);
        $order = $this->order($danna, 'ORD-AAA111');
        $danna->delete();
        $this->actingAsAdmin();

        Livewire::test(ListOrders::class)
            ->searchTable('danna')
            ->assertCanSeeTableRecords([$order]);
    }

    public function test_orders_table_can_be_sorted_by_customer(): void
    {
        $this->order(Customer::factory()->create(['first_name' => 'Zed']), 'ORD-1');
        $this->order(Customer::factory()->create(['first_name' => 'Amy']), 'ORD-2');
        $this->actingAsAdmin();

        Livewire::test(ListOrders::class)
            ->sortTable('customer.name')
            ->assertSuccessful();
    }

    public function test_every_admin_table_survives_a_search(): void
    {
        $this->actingAsAdmin();

        $pages = [
            ListActivityLogs::class,
            ListBanners::class,
            ListCategories::class,
            ListCustomers::class,
            ListOrders::class,
            ListProducts::class,
            ListReports::class,
            ListReturnRefunds::class,
            ListReviews::class,
            ListThemes::class,
            ListUsers::class,
            InventoryManagement::class,
            UnitSold::class,
        ];

        foreach ($pages as $page) {
            foreach (['danna', 'danna cruz', "o'brien", '50%'] as $term) {
                Livewire::test($page)
                    ->searchTable($term)
                    ->assertSuccessful();
            }
        }
    }

    public function test_admin_tables_list_the_newest_record_first(): void
    {
        $this->actingAsAdmin();

        $old = Customer::factory()->create(['created_at' => now()->subDays(3)]);
        $new = Customer::factory()->create(['created_at' => now()]);
        $oldOrder = $this->order($old, 'ORD-OLD');
        $newOrder = $this->order($new, 'ORD-NEW');
        $oldOrder->forceFill(['created_at' => now()->subDays(3)])->saveQuietly();

        Livewire::test(ListOrders::class)
            ->assertCanSeeTableRecords([$newOrder, $oldOrder], inOrder: true);

        Livewire::test(ListCustomers::class)
            ->assertCanSeeTableRecords([$new, $old], inOrder: true);
    }
}
