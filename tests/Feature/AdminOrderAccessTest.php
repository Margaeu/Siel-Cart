<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class AdminOrderAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel('admin');
    }

    public function test_admins_cannot_create_orders(): void
    {
        $this->assertFalse(OrderResource::canCreate());
        $this->assertArrayNotHasKey('create', OrderResource::getPages());
        $this->assertFalse(Route::has('filament.admin.resources.orders.create'));
    }

    public function test_orders_list_does_not_show_a_create_action(): void
    {
        Livewire::test(ListOrders::class)
            ->assertOk()
            ->assertDontSeeText('New order');
    }
}
