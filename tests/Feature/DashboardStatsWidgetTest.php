<?php

namespace Tests\Feature;

use App\Filament\Widgets\StatsOverview;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardStatsWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel('admin');
    }

    public function test_dashboard_displays_the_five_requested_metrics(): void
    {
        $customer = Customer::factory()->create();

        Order::create([
            'customer_id' => $customer->id,
            'subtotal' => 150.25,
            'total' => 150.25,
            'payment_status' => 'paid',
            'status' => 'completed',
        ]);

        Order::create([
            'customer_id' => $customer->id,
            'subtotal' => 75,
            'total' => 75,
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);

        $category = Category::factory()->create();

        Product::factory()->for($category)->create([
            'has_variants' => false,
            'stock_quantity' => 3,
            'low_stock_threshold' => 5,
        ]);

        Product::factory()->for($category)->create([
            'has_variants' => false,
            'stock_quantity' => 20,
            'low_stock_threshold' => 5,
        ]);

        Livewire::test(StatsOverview::class)
            ->assertOk()
            ->assertSeeText('Total sales')
            ->assertSeeText('₱150.25')
            ->assertSeeText('Pending orders')
            ->assertSeeText('Products')
            ->assertSeeText('Customers')
            ->assertSeeText('Low-stock products')
            ->assertSeeText('Restock required')
            ->assertSeeHtml('clsu-stat--green')
            ->assertSeeHtml('clsu-stat--yellow')
            ->assertSeeHtml('clsu-stat--critical');
    }
}
