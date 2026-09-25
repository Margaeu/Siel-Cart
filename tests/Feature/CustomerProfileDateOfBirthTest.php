<?php

namespace Tests\Feature;

use App\Livewire\Customer\Profile;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A customer can edit their own birthday from the storefront profile page,
 * but the same 13-year minimum age CreateNewCustomer enforces at
 * registration applies here too -- editing it is not a way to slip under
 * that floor. The admin CustomerForm mirrors the same rule via maxDate().
 *
 * Every updateProfile() call here also fills current_password_for_profile:
 * that field is unrelated to date_of_birth (it's the account's identity
 * re-confirmation before any profile edit), but updateProfile() validates
 * it in the same pass, so it has to be correct for these DOB-focused
 * assertions to isolate what they're actually testing.
 */
class CustomerProfileDateOfBirthTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'secret-password';

    private function customer(?string $dateOfBirth = '2000-01-15'): Customer
    {
        return Customer::factory()->create([
            'password' => Hash::make(self::PASSWORD),
            'date_of_birth' => $dateOfBirth,
        ]);
    }

    public function test_customer_can_update_their_date_of_birth(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');

        $newDob = now()->subYears(25)->startOfDay()->format('Y-m-d');

        Livewire::test(Profile::class)
            ->set('date_of_birth', $newDob)
            ->set('current_password_for_profile', self::PASSWORD)
            ->call('updateProfile')
            ->assertHasNoErrors();

        $this->assertSame($newDob, $customer->fresh()->date_of_birth);
    }

    public function test_customer_cannot_set_a_date_of_birth_under_thirteen_years_old(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');

        $tooYoung = now()->subYears(13)->addDay()->format('Y-m-d');

        Livewire::test(Profile::class)
            ->set('date_of_birth', $tooYoung)
            ->set('current_password_for_profile', self::PASSWORD)
            ->call('updateProfile')
            ->assertHasErrors(['date_of_birth' => 'before_or_equal']);

        // Untouched: the original birthday, not the rejected one.
        $this->assertSame('2000-01-15', $customer->fresh()->date_of_birth);
    }

    public function test_a_date_of_birth_exactly_thirteen_years_ago_is_allowed(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');

        $exactlyThirteen = now()->subYears(13)->format('Y-m-d');

        Livewire::test(Profile::class)
            ->set('date_of_birth', $exactlyThirteen)
            ->set('current_password_for_profile', self::PASSWORD)
            ->call('updateProfile')
            ->assertHasNoErrors();

        $this->assertSame($exactlyThirteen, $customer->fresh()->date_of_birth);
    }

    public function test_customer_can_clear_their_date_of_birth(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');

        Livewire::test(Profile::class)
            ->set('date_of_birth', '')
            ->set('current_password_for_profile', self::PASSWORD)
            ->call('updateProfile')
            ->assertHasNoErrors();

        $this->assertNull($customer->fresh()->date_of_birth);
    }

    public function test_a_customer_with_no_date_of_birth_can_update_other_fields_without_setting_one(): void
    {
        $customer = $this->customer(dateOfBirth: null);
        $this->actingAs($customer, 'customer');

        Livewire::test(Profile::class)
            ->set('phone', '09171234567')
            ->set('current_password_for_profile', self::PASSWORD)
            ->call('updateProfile')
            ->assertHasNoErrors();

        $this->assertNull($customer->fresh()->date_of_birth);
        $this->assertSame('09171234567', $customer->fresh()->phone);
    }
}
