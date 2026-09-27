<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            // Philippine mobile format (11 digits, 09xxxxxxxxx): the store is a CLSU
            // campus shop, so a US number was never right, and fake()->phoneNumber()
            // was actively breaking a test. Filament's ->tel() on the admin
            // CustomerForm's phone field adds a regex rule of its own
            // (TextInput::tel() calls regex(getTelRegex())), and that pattern
            // allows '+', 1-4 digits, then only [-\s./0-9] -- so it rejects
            // every '+1 (272) 779-2495' shape Faker produces about 7.6% of the
            // time. AdminCustomerBirthdateTest edits only date_of_birth, but
            // save() revalidates the whole hydrated form, so that phone failed
            // assertHasNoFormErrors() in roughly 1 run in 13. The rest of the
            // suite already hardcodes 09171234567 for this reason.
            'phone' => '09'.fake()->numerify('#########'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the customer's email address is unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
