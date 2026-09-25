<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerLoginFailureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The email is deliberately kept (old('email') in login.blade.php),
     * not cleared: standard form-repopulation UX, so a mistyped password
     * doesn't also cost the customer their email.
     */
    public function test_failed_login_shows_an_error_and_keeps_the_submitted_email(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'nobody@example.com',
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'));

        $this->get(route('login'))
            ->assertSee(__('auth.failed'))
            ->assertSee('nobody@example.com');
    }

    private function attemptLogin(string $email, string $password): array
    {
        $response = $this->from(route('login'))
            ->post(route('login.store'), ['email' => $email, 'password' => $password]);

        $observed = [
            'status' => $response->getStatusCode(),
            'location' => $response->headers->get('Location'),
            'errors' => session('errors')?->getBag('default')->toArray() ?? [],
        ];

        $this->flushSession();

        return $observed;
    }

    public function test_unknown_email_and_wrong_password_get_the_same_response(): void
    {
        Customer::factory()->create([
            'email' => 'registered@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $wrongPassword = $this->attemptLogin('registered@example.com', 'not-the-password');
        $unknownEmail = $this->attemptLogin('unregistered@example.com', 'not-the-password');

        $this->assertSame($wrongPassword, $unknownEmail);
        $this->assertSame(['email' => [__('auth.failed')]], $unknownEmail['errors']);
        $this->assertGuest('customer');
    }

    /**
     * The lockout used to be counted only for registered emails, so reaching it
     * confirmed the account existed even once the messages matched.
     */
    public function test_unknown_email_and_wrong_password_lock_out_on_the_same_attempt(): void
    {
        Customer::factory()->create([
            'email' => 'registered@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        foreach (['registered@example.com', 'unregistered@example.com'] as $email) {
            $responses = [];

            for ($i = 0; $i < 5; $i++) {
                $responses[] = $this->attemptLogin($email, 'not-the-password')['errors']['email'][0] ?? null;
            }

            $this->assertSame(array_fill(0, 4, __('auth.failed')), array_slice($responses, 0, 4), $email);
            $this->assertStringStartsWith('Too many failed login attempts.', $responses[4], $email);
        }
    }

    public function test_the_correct_password_still_logs_in_after_failures(): void
    {
        $customer = Customer::factory()->create([
            'password' => Hash::make('secret-password'),
        ]);

        $this->attemptLogin($customer->email, 'not-the-password');

        $this->post(route('login.store'), [
            'email' => $customer->email,
            'password' => 'secret-password',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($customer, 'customer');
    }
}
