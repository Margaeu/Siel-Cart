<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ReturnRefunds\Pages\CreateReturnRefund;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReturnRefundOrderSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_selector_finds_or_numbers_and_shows_them_in_the_option_and_selection(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        Gate::before(fn () => true);
        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        $customer = Customer::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
        $completed = Order::create([
            'order_number' => 'ORD-COLLECTED',
            'customer_id' => $customer->id,
            'subtotal' => 350,
            'total' => 350,
            'status' => 'completed',
            'or_number' => 'OR-2345',
        ]);
        $pending = Order::create([
            'order_number' => 'ORD-PENDING',
            'customer_id' => $customer->id,
            'subtotal' => 350,
            'total' => 350,
            'status' => 'pending',
            'or_number' => 'OR-2346',
        ]);

        $page = Livewire::test(CreateReturnRefund::class);
        $select = $page->instance()->form->getComponent('order_id');

        $this->assertInstanceOf(Select::class, $select);
        $this->assertSame(
            [$completed->id => 'ORD-COLLECTED — OR Number: OR-2345 — Maria Santos'],
            $select->getSearchResults('2345'),
        );
        $this->assertArrayNotHasKey($pending->id, $select->getSearchResults('2346'));

        $page->set('data.order_id', $completed->id);
        $select = $page->instance()->form->getComponent('order_id');
        $this->assertSame('ORD-COLLECTED — OR Number: OR-2345 — Maria Santos', $select->getOptionLabel());
    }
}
