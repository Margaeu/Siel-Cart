<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Logout used to call session()->regenerate(), which changes the session id but
 * carries every stored value over to the new session. It now invalidates the
 * session and issues a fresh CSRF token.
 */
class CustomerLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_ends_authentication(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')
            ->post(route('logout'))
            ->assertRedirect('/');

        $this->assertGuest('customer');

        $this->get(route('customer.dashboard'))->assertRedirect(route('login'));
    }

    public function test_logout_invalidates_the_session_and_regenerates_the_csrf_token(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')
            ->withSession(['url.intended' => '/checkout', 'left-behind' => 'value']);

        $session = $this->app['session.store'];
        $session->start();
        $idBefore = $session->getId();
        $tokenBefore = $session->token();

        $this->post(route('logout'));

        $this->assertNotSame($idBefore, $session->getId());
        $this->assertNotSame($tokenBefore, $session->token());
        $this->assertFalse($session->has('left-behind'));
        $this->assertFalse($session->has('url.intended'));
    }

    public function test_a_guest_cannot_post_to_logout(): void
    {
        $this->post(route('logout'))->assertRedirect(route('login'));
    }
}
