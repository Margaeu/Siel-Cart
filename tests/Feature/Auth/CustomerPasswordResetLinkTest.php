<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Covers ResetPasswordController::showResetForm()'s own token check, which
 * runs before the customer ever sees the reset form - separate from
 * PasswordResetTest, which only exercises the happy path.
 */
class CustomerPasswordResetLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_reset_link_still_shows_the_form(): void
    {
        $customer = Customer::factory()->create();
        $token = Password::broker('customers')->createToken($customer);

        $this->get(route('password.reset', ['token' => $token, 'email' => $customer->email]))
            ->assertOk()
            ->assertViewIs('auth.customer.reset-password');
    }

    public function test_an_expired_reset_link_shows_the_branded_expired_page(): void
    {
        $customer = Customer::factory()->create();
        $token = Password::broker('customers')->createToken($customer);

        Password::broker('customers')->deleteToken($customer);

        $this->get(route('password.reset', ['token' => $token, 'email' => $customer->email]))
            ->assertStatus(419)
            ->assertViewIs('errors.link-expired')
            ->assertSee('This reset link has expired')
            ->assertSee('Request a new reset link');
    }

    public function test_a_malformed_reset_link_shows_the_same_branded_expired_page(): void
    {
        $this->get('/reset-password/some-token')
            ->assertStatus(419)
            ->assertViewIs('errors.link-expired');
    }
}
