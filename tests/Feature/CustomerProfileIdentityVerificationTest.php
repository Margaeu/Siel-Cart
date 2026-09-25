<?php

namespace Tests\Feature;

use App\Livewire\Customer\Profile;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerProfileIdentityVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'secret-password';

    private function customer(): Customer
    {
        return Customer::factory()->create([
            'first_name' => 'Original',
            'password' => Hash::make(self::PASSWORD),
        ]);
    }

    public function test_profile_page_prompts_for_identity_verification(): void
    {
        $this->actingAs($this->customer(), 'customer');

        Livewire::test(Profile::class)
            ->assertSeeText('Identity Verification')
            ->assertSeeText('Enter your current password to confirm it is you before saving these profile changes.')
            ->assertSeeHtml('autocomplete="current-password"');
    }

    public function test_profile_changes_are_not_saved_without_a_current_password(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');

        Livewire::test(Profile::class)
            ->set('first_name', 'Changed')
            ->call('updateProfile')
            ->assertHasErrors(['current_password_for_profile' => 'required']);

        $this->assertSame('Original', $customer->fresh()->first_name);
    }

    public function test_profile_changes_are_not_saved_with_an_incorrect_password(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');

        Livewire::test(Profile::class)
            ->set('first_name', 'Changed')
            ->set('current_password_for_profile', 'wrong-password')
            ->call('updateProfile')
            ->assertHasErrors(['current_password_for_profile']);

        $this->assertSame('Original', $customer->fresh()->first_name);
    }

    public function test_correct_password_saves_profile_changes_and_closes_the_prompt(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');

        Livewire::test(Profile::class)
            ->set('first_name', 'Changed')
            ->set('current_password_for_profile', self::PASSWORD)
            ->call('updateProfile')
            ->assertHasNoErrors()
            ->assertSet('current_password_for_profile', null)
            ->assertDispatched('profile-updated');

        $this->assertSame('Changed', $customer->fresh()->first_name);
    }

    public function test_cancelling_verification_clears_the_password_and_its_error(): void
    {
        $this->actingAs($this->customer(), 'customer');

        Livewire::test(Profile::class)
            ->set('current_password_for_profile', 'wrong-password')
            ->call('updateProfile')
            ->assertHasErrors(['current_password_for_profile'])
            ->call('cancelProfileVerification')
            ->assertSet('current_password_for_profile', null)
            ->assertHasNoErrors('current_password_for_profile');
    }
}
