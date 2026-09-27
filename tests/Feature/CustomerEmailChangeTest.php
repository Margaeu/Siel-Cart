<?php

namespace Tests\Feature;

use App\Livewire\Customer\Profile;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\CustomerConfirmEmailChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Changing a customer's email is a two-step confirm flow, mirroring how
 * Google/GitHub handle it: requesting the change never writes to the
 * `email` column, only `pending_email`, and only the signed link mailed to
 * the NEW address (never the old one) can land it. See
 * App\Livewire\Customer\Profile::requestEmailChange() and
 * App\Http\Controllers\Auth\ConfirmEmailChangeController.
 */
class CustomerEmailChangeTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'secret-password';

    private function customer(): Customer
    {
        return Customer::factory()->create([
            'email' => 'original@example.com',
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
        ]);
    }

    public function test_requesting_an_email_change_stores_pending_email_and_mails_the_new_address(): void
    {
        Notification::fake();

        $customer = $this->customer();

        Livewire::actingAs($customer, 'customer')
            ->test(Profile::class)
            ->set('new_email', 'new@example.com')
            ->set('current_password_for_email', self::PASSWORD)
            ->call('requestEmailChange')
            ->assertHasNoErrors();

        $customer->refresh();

        $this->assertSame('original@example.com', $customer->email);
        $this->assertSame('new@example.com', $customer->pending_email);

        Notification::assertSentOnDemand(
            CustomerConfirmEmailChange::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'new@example.com'
        );
    }

    public function test_requesting_an_email_change_requires_the_correct_current_password(): void
    {
        Notification::fake();

        $customer = $this->customer();

        Livewire::actingAs($customer, 'customer')
            ->test(Profile::class)
            ->set('new_email', 'new@example.com')
            ->set('current_password_for_email', 'wrong-password')
            ->call('requestEmailChange')
            ->assertHasErrors('current_password_for_email');

        $this->assertNull($customer->refresh()->pending_email);
        Notification::assertNothingSent();
    }

    public function test_requesting_an_email_change_rejects_an_address_already_in_use(): void
    {
        Customer::factory()->create(['email' => 'taken@example.com']);
        $customer = $this->customer();

        Livewire::actingAs($customer, 'customer')
            ->test(Profile::class)
            ->set('new_email', 'taken@example.com')
            ->set('current_password_for_email', self::PASSWORD)
            ->call('requestEmailChange')
            ->assertHasErrors('new_email');

        $this->assertNull($customer->refresh()->pending_email);
    }

    public function test_requesting_an_email_change_rejects_an_admins_address(): void
    {
        User::factory()->create(['email' => 'admin@example.com']);
        $customer = $this->customer();

        Livewire::actingAs($customer, 'customer')
            ->test(Profile::class)
            ->set('new_email', 'admin@example.com')
            ->set('current_password_for_email', self::PASSWORD)
            ->call('requestEmailChange')
            ->assertHasErrors(['new_email' => 'unique']);

        $this->assertNull($customer->refresh()->pending_email);
    }

    public function test_confirming_the_signed_link_moves_pending_email_into_the_live_email_and_marks_it_verified(): void
    {
        $customer = $this->customer();
        $customer->forceFill(['pending_email' => 'new@example.com'])->save();

        $url = URL::temporarySignedRoute('customer.email.confirm', now()->addHour(), [
            'customer' => $customer->id,
            'hash' => sha1('new@example.com'),
        ]);

        $response = $this->actingAs($customer, 'customer')->get($url);

        $customer->refresh();
        $this->assertSame('new@example.com', $customer->email);
        $this->assertNull($customer->pending_email);
        $this->assertNotNull($customer->email_verified_at);
        $response->assertRedirect(route('customer.profile'));
    }

    public function test_confirmation_rejects_an_address_taken_by_an_admin_after_the_request(): void
    {
        $customer = $this->customer();
        $customer->forceFill(['pending_email' => 'new@example.com'])->save();
        User::factory()->create(['email' => 'new@example.com']);

        $url = URL::temporarySignedRoute('customer.email.confirm', now()->addHour(), [
            'customer' => $customer->id,
            'hash' => sha1('new@example.com'),
        ]);

        $response = $this->actingAs($customer, 'customer')->get($url);

        $this->assertSame('original@example.com', $customer->refresh()->email);
        $this->assertNull($customer->pending_email);
        $response->assertRedirect(route('customer.profile'));
        $response->assertSessionHas('email_change_error');
    }

    public function test_confirming_with_a_tampered_hash_is_rejected_and_leaves_the_pending_change_in_place(): void
    {
        $customer = $this->customer();
        $customer->forceFill(['pending_email' => 'new@example.com'])->save();

        // Same customer id, but a hash for a different (unrequested) address.
        $url = URL::temporarySignedRoute('customer.email.confirm', now()->addHour(), [
            'customer' => $customer->id,
            'hash' => sha1('attacker@example.com'),
        ]);

        $this->get($url);

        $customer->refresh();
        $this->assertSame('original@example.com', $customer->email);
        $this->assertSame('new@example.com', $customer->pending_email);
    }

    public function test_confirming_an_expired_link_is_rejected(): void
    {
        $customer = $this->customer();
        $customer->forceFill(['pending_email' => 'new@example.com'])->save();

        $url = URL::temporarySignedRoute('customer.email.confirm', now()->subMinute(), [
            'customer' => $customer->id,
            'hash' => sha1('new@example.com'),
        ]);

        $response = $this->get($url);

        $response->assertForbidden();
        $this->assertSame('new@example.com', $customer->refresh()->pending_email);
    }

    public function test_a_tampered_link_clicked_while_logged_in_as_the_target_shows_the_error_on_the_profile_page(): void
    {
        $customer = $this->customer();
        $customer->forceFill(['pending_email' => 'new@example.com'])->save();

        $url = URL::temporarySignedRoute('customer.email.confirm', now()->addHour(), [
            'customer' => $customer->id,
            'hash' => sha1('attacker@example.com'),
        ]);

        // RedirectIfAuthenticated would otherwise bounce a bare
        // route('login') redirect straight to the dashboard for an
        // already-authenticated customer, silently dropping the flash —
        // this asserts ConfirmEmailChangeController routes the error to
        // the profile page instead when that's who is signed in.
        $response = $this->actingAs($customer, 'customer')->get($url);

        $response->assertRedirect(route('customer.profile'));
        $response->assertSessionHas('email_change_error');
        $this->assertSame('new@example.com', $customer->refresh()->pending_email);
    }

    public function test_cancelling_a_pending_change_clears_it_without_sending_anything(): void
    {
        Notification::fake();

        $customer = $this->customer();
        $customer->forceFill(['pending_email' => 'new@example.com'])->save();

        Livewire::actingAs($customer, 'customer')
            ->test(Profile::class)
            ->call('cancelPendingEmailChange');

        $this->assertNull($customer->refresh()->pending_email);
        Notification::assertNothingSent();
    }

    public function test_masked_email_accessor_stars_out_the_middle_of_the_local_part(): void
    {
        $customer = Customer::factory()->make(['email' => 'margaret123@gmail.com']);

        $this->assertSame('ma********3@gmail.com', $customer->masked_email);
    }
}
