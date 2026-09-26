<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fortify registers GET/POST /user/confirm-password whether or not anything
 * uses the password.confirm middleware. Its page used to be the Flux
 * starter-kit view (livewire/auth/confirm-password), which broke nothing only
 * because nobody visited it; when livewire/flux was removed the view was
 * rebuilt as a plain customer auth page. These pin that the route still
 * renders it and that the form still posts to a working endpoint.
 */
class CustomerConfirmPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_page_renders_the_customer_view(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')
            ->get(route('password.confirm'))
            ->assertOk()
            ->assertViewIs('auth.customer.confirm-password')
            ->assertSee('Confirm your password')
            ->assertSee(route('password.confirm.store'), false);
    }

    public function test_correct_password_is_confirmed(): void
    {
        $customer = Customer::factory()->create(['password' => Hash::make('secret-pass')]);

        $this->actingAs($customer, 'customer')
            ->post(route('password.confirm.store'), ['password' => 'secret-pass'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('auth.password_confirmed_at');
    }

    public function test_wrong_password_is_rejected_on_the_password_field(): void
    {
        $customer = Customer::factory()->create(['password' => Hash::make('secret-pass')]);

        $this->actingAs($customer, 'customer')
            ->from(route('password.confirm'))
            ->post(route('password.confirm.store'), ['password' => 'not-it'])
            ->assertRedirect(route('password.confirm'))
            ->assertSessionHasErrors('password')
            ->assertSessionMissing('auth.password_confirmed_at');
    }
}
