<?php

namespace Tests\Feature;

use App\Livewire\Customer\Profile;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

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

    public function test_profile_displays_the_masked_date_of_birth_without_an_edit_control(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');

        Livewire::test(Profile::class)
            ->assertSeeText('Date of Birth')
            ->assertSeeText('**/**/2000')
            ->assertSeeText('Date of birth cannot be changed through Profile Management.')
            ->assertDontSeeHtml('name="date_of_birth"')
            ->assertDontSeeHtml('wire:model="date_of_birth"');
    }

    public function test_a_tampered_component_value_cannot_change_the_date_of_birth(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');

        $attemptedDob = now()->subYears(25)->format('Y-m-d');

        Livewire::test(Profile::class)
            ->set('date_of_birth', $attemptedDob)
            ->set('phone', '09171234567')
            ->set('current_password_for_profile', self::PASSWORD)
            ->call('updateProfile')
            ->assertHasNoErrors();

        $this->assertSame('2000-01-15', $customer->fresh()->date_of_birth);
        $this->assertSame('09171234567', $customer->fresh()->phone);
    }

    public function test_a_customer_with_no_date_of_birth_can_update_other_fields_without_setting_one(): void
    {
        $customer = $this->customer(dateOfBirth: null);
        $this->actingAs($customer, 'customer');

        Livewire::test(Profile::class)
            ->assertSeeText('Not provided')
            ->assertDontSeeHtml('name="date_of_birth"')
            ->set('phone', '09171234567')
            ->set('current_password_for_profile', self::PASSWORD)
            ->call('updateProfile')
            ->assertHasNoErrors();

        $this->assertNull($customer->fresh()->date_of_birth);
        $this->assertSame('09171234567', $customer->fresh()->phone);
    }
}
