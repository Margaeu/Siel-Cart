<?php

namespace Tests\Feature\Auth;

use App\Actions\Fortify\CreateNewCustomer;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerRegistrationEmailUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_registration_rejects_an_admins_email(): void
    {
        User::factory()->create(['email' => 'admin@example.com']);

        try {
            app(CreateNewCustomer::class)->create([
                'first_name' => 'New',
                'last_name' => 'Customer',
                'email' => 'admin@example.com',
                'date_of_birth' => '2000-01-01',
                'password' => 'Password123!',
            ]);

            $this->fail('Registration should reject an email already used by an admin.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        $this->assertFalse(Customer::where('email', 'admin@example.com')->exists());
    }
}
