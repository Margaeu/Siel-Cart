<?php

namespace Tests\Feature;

use App\Livewire\Customer\Profile;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerPhoneValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_accepts_eleven_digits_and_an_optional_blank_phone(): void
    {
        $customer = Customer::factory()->create(['password' => Hash::make('phone-test-password')]);
        $this->actingAs($customer, 'customer');

        foreach (['09171234567', ''] as $phone) {
            Livewire::test(Profile::class)
                ->set('phone', $phone)
                ->set('current_password_for_profile', 'phone-test-password')
                ->call('updateProfile')->assertHasNoErrors();

            $this->assertEquals($phone, $customer->fresh()->phone);
        }
    }

    public function test_profile_rejects_overlong_and_non_numeric_phones_without_saving(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '09171234567', 'password' => Hash::make('phone-test-password'),
        ]);
        $this->actingAs($customer, 'customer');

        foreach (['091712345678', 'not-a-phone', '0917abc4567', '0917 1234567'] as $phone) {
            Livewire::test(Profile::class)
                ->set('phone', $phone)
                ->set('current_password_for_profile', 'phone-test-password')
                ->call('updateProfile')->assertHasErrors('phone');

            $this->assertSame('09171234567', $customer->fresh()->phone);
        }
    }

    public function test_registration_rejects_overlong_and_non_numeric_phones(): void
    {
        foreach (['091712345678', 'not-a-phone'] as $phone) {
            $this->post(route('register.store'), $this->registration($phone))
                ->assertSessionHasErrors('phone');
            $this->assertDatabaseMissing('customers', ['email' => 'phone-test@example.com']);
        }
    }

    public function test_registration_accepts_eleven_digit_phone(): void
    {
        Notification::fake();
        $this->post(route('register.store'), $this->registration('09171234567'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', ['email' => 'phone-test@example.com', 'phone' => '09171234567']);
    }

    private function registration(string $phone): array
    {
        return [
            'first_name' => 'Phone', 'last_name' => 'Test', 'email' => 'phone-test@example.com',
            'date_of_birth' => '2000-01-01', 'phone' => $phone,
            'password' => 'phone-test-password', 'password_confirmation' => 'phone-test-password',
        ];
    }
}
