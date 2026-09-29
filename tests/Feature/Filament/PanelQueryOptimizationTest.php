<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\ReturnRefunds\Pages\CreateReturnRefund;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PanelQueryOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        Gate::before(fn () => true);
        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);
    }

    public function test_order_status_badges_share_one_query_and_exclude_trashed_orders(): void
    {
        $this->actingAsAdmin();
        $customer = Customer::factory()->create();

        foreach (['pending', 'pending', 'processing', 'completed', 'cancelled'] as $index => $status) {
            Order::create([
                'order_number' => "ORD-BADGE-{$index}",
                'customer_id' => $customer->id,
                'subtotal' => 100,
                'total' => 100,
                'status' => $status,
            ]);
        }

        Order::create([
            'order_number' => 'ORD-BADGE-TRASHED',
            'customer_id' => $customer->id,
            'subtotal' => 100,
            'total' => 100,
            'status' => 'pending',
        ])->delete();

        $tabs = (new ListOrders)->getTabs();

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->assertSame('2', $tabs['pending']->getBadge());
        $this->assertSame('1', $tabs['processing']->getBadge());
        $this->assertSame('0', $tabs['ready-for-pickup']->getBadge());
        $this->assertSame('1', $tabs['completed']->getBadge());
        $this->assertSame('1', $tabs['cancelled']->getBadge());
        $this->assertSame('0', $tabs['returns']->getBadge());
        $this->assertSame('0', $tabs['returns']->getBadge());

        $this->assertCount(2, DB::getQueryLog());

        DB::disableQueryLog();
    }

    public function test_return_form_searches_products_without_loading_the_catalog(): void
    {
        $this->actingAsAdmin();
        $matching = Product::factory()->create(['name' => 'Green CLSU Mug']);
        Product::factory()->create(['name' => 'Blue CLSU Shirt']);

        $page = Livewire::test(CreateReturnRefund::class);
        $select = $page->instance()->form->getComponent('incorrect_product_id', withHidden: true);

        $this->assertSame([], $select->getOptions());
        $this->assertSame([$matching->id => 'Green CLSU Mug'], $select->getSearchResults('Mug'));

        $page->set('data.incorrect_product_id', $matching->id);
        $select = $page->instance()->form->getComponent('incorrect_product_id', withHidden: true);
        $this->assertSame('Green CLSU Mug', $select->getOptionLabel());
    }

    public function test_return_form_reuses_the_selected_item_during_a_request(): void
    {
        $this->actingAsAdmin();
        $customer = Customer::factory()->create();
        $order = Order::create([
            'order_number' => 'ORD-ITEM-CACHE',
            'customer_id' => $customer->id,
            'subtotal' => 100,
            'total' => 100,
            'status' => 'completed',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'CLSU Mug',
            'price' => 100,
            'quantity' => 2,
            'subtotal' => 200,
        ]);

        $page = Livewire::test(CreateReturnRefund::class)
            ->set('data.order_id', $order->id)
            ->set('data.order_item_id', $item->id);
        $quantity = $page->instance()->form->getComponent('quantity');

        request()->attributes->remove("App\\Filament\\Resources\\ReturnRefunds\\Schemas\\ReturnRefundForm:selected-item:{$order->id}:{$item->id}");
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->assertSame(2, $quantity->getMaxValue());
        $this->assertSame(2, $quantity->getMaxValue());

        $itemQueries = array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], 'from "order_items"'));
        $this->assertCount(1, $itemQueries);

        DB::disableQueryLog();
    }
}
