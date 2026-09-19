<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $response = $this->actingAs($customer, 'customer')->get(route('verification.notice'));

        $response->assertStatus(200);
    }

    public function test_unverified_customer_is_redirected_to_the_verification_screen(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $response = $this->actingAs($customer, 'customer')->get(route('customer.dashboard'));

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_customer_is_told_why_checkout_was_blocked(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $response = $this->actingAs($customer, 'customer')->get(route('checkout'));

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHas('verify_reason');
        $this->assertStringContainsString(
            'checking out',
            session('verify_reason'),
        );
    }

    public function test_customer_is_returned_to_checkout_after_verifying(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $this->actingAs($customer, 'customer')->get(route('checkout'));

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $customer->id, 'hash' => sha1($customer->email)]
        );

        $response = $this->actingAs($customer, 'customer')->get($verificationUrl);

        $response->assertRedirect(route('checkout'));
    }

    public function test_verified_customer_passes_the_verification_gate(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer, 'customer')->get(route('checkout'));

        // An empty cart sends them to the cart page, but the point is that the
        // verification middleware let the request through.
        $response->assertRedirect(route('cart.index'));
        $response->assertSessionMissing('verify_reason');
    }

    public function test_unverified_customer_can_log_out(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $response = $this->actingAs($customer, 'customer')->post(route('logout'));

        $response->assertRedirect('/');
        $this->assertGuest('customer');
    }

    public function test_customer_can_request_another_verification_email(): void
    {
        Notification::fake();

        $customer = Customer::factory()->unverified()->create();

        $response = $this->actingAs($customer, 'customer')->post(route('verification.send'));

        $response->assertSessionHas('status', 'verification-link-sent');
        Notification::assertSentTo($customer, VerifyEmail::class);
    }

    /**
     * A GET that sends mail can be triggered by a link, an <img> tag, or a
     * browser prefetch, none of which carry a CSRF token.
     */
    public function test_a_get_request_cannot_send_a_verification_email(): void
    {
        Notification::fake();

        $customer = Customer::factory()->unverified()->create();

        $this->actingAs($customer, 'customer')
            ->get(route('verification.send'))
            ->assertMethodNotAllowed();

        Notification::assertNothingSent();
    }

    public function test_a_guest_cannot_request_a_verification_email(): void
    {
        Notification::fake();

        $this->post(route('verification.send'))
            ->assertRedirect(route('login'));

        Notification::assertNothingSent();
    }

    public function test_verification_email_requests_are_throttled(): void
    {
        Notification::fake();

        $customer = Customer::factory()->unverified()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($customer, 'customer')
                ->post(route('verification.send'))
                ->assertSessionHas('status', 'verification-link-sent');
        }

        $this->actingAs($customer, 'customer')
            ->post(route('verification.send'))
            ->assertTooManyRequests();

        Notification::assertSentToTimes($customer, VerifyEmail::class, 6);
    }

    public function test_email_can_be_verified(): void
    {
        $customer = Customer::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $customer->id, 'hash' => sha1($customer->email)]
        );

        $response = $this->actingAs($customer, 'customer')->get($verificationUrl);

        Event::assertDispatched(Verified::class);

        $this->assertTrue($customer->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('customer.dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $customer->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($customer, 'customer')->get($verificationUrl);

        $this->assertFalse($customer->fresh()->hasVerifiedEmail());
    }
}
