<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use App\Notifications\CustomerResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * POST /forgot-password used to echo the password broker's status, which said
 * "We can't find a user with that email address." for unknown emails and only
 * ever said "Please wait before retrying." for real accounts. Either message
 * confirmed whether an address was registered.
 */
class ForgotPasswordEnumerationTest extends TestCase
{
    use RefreshDatabase;

    private function requestLink(string $email): TestResponse
    {
        return $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $email]);
    }

    /**
     * Everything a caller can observe from the response: where it redirects,
     * what it flashes, and which errors it carries.
     */
    private function observable(TestResponse $response): array
    {
        return [
            'status' => $response->getStatusCode(),
            'location' => $response->headers->get('Location'),
            'flash' => session('status'),
            'errors' => session('errors')?->getBag('default')->all() ?? [],
        ];
    }

    public function test_a_registered_customer_still_receives_a_working_reset_link(): void
    {
        Notification::fake();

        $customer = Customer::factory()->create();

        $this->requestLink($customer->email)
            ->assertRedirect(route('password.request'))
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($customer, CustomerResetPassword::class, function (CustomerResetPassword $notification) use ($customer) {
            return app('auth.password')->broker('customers')->tokenExists($customer, $notification->token);
        });
    }

    public function test_the_response_is_the_same_for_registered_and_unknown_emails(): void
    {
        Notification::fake();

        $customer = Customer::factory()->create();

        $registered = $this->observable($this->requestLink($customer->email));

        $this->flushSession();

        $unknown = $this->observable($this->requestLink('nobody-here@example.com'));

        $this->assertSame($registered, $unknown);
        $this->assertSame([], $unknown['errors']);
        $this->assertNotNull($unknown['flash']);
        $this->assertStringNotContainsStringIgnoringCase("can't find", $unknown['flash']);
    }

    /**
     * The broker only throttles tokens for accounts that exist, so a second
     * request within the broker's window must not come back any different.
     */
    public function test_a_broker_throttled_request_does_not_reveal_the_account(): void
    {
        Notification::fake();

        $customer = Customer::factory()->create();

        $first = $this->observable($this->requestLink($customer->email));

        $this->flushSession();

        $second = $this->observable($this->requestLink($customer->email));

        $this->assertSame($first, $second);
        Notification::assertSentToTimes($customer, CustomerResetPassword::class, 1);
    }

    public function test_reset_link_requests_are_throttled(): void
    {
        Notification::fake();

        for ($i = 0; $i < 6; $i++) {
            $this->requestLink("nobody{$i}@example.com")->assertRedirect(route('password.request'));
        }

        $this->requestLink('nobody-else@example.com')->assertTooManyRequests();
    }
}
