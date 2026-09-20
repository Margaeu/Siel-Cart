<?php

namespace App\Actions\Fortify;

use App\Models\Customer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewCustomer implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input)
    {
        $messages = [
            'date_of_birth.before_or_equal' => 'You must be 13 years old to create an account.',
        ];

        Validator::make($input, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(Customer::class),
            ],

            // Date of birth is required when creating a new customer account.
            // The customer must be at least 13 years old.
            'date_of_birth' => ['required', 'date', 'before_or_equal:' . now()->subYears(13)->format('Y-m-d')],

            'password' => $this->passwordRules(),
            'phone' => ['nullable', 'string', 'max:255'],
        ], $messages)->validate();

        $customer = Customer::create([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'phone' => $input['phone'] ?? null,
            // Save the date of birth entered in the registration form.
            'date_of_birth' => $input['date_of_birth'],
            'is_active' => true,
        ]);

        return $customer;
    }
}