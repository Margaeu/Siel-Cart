<?php

namespace App\Actions\Fortify;

use App\Models\Customer;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the customer's forgotten password.
     *
     * Fortify's guard is set to "customer" (see config/fortify.php), so the
     * $user Fortify resolves here is always a Customer instance.
     *
     * @param  array<string, string>  $input
     */
    public function reset(Customer $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
        ])->save();
    }
}
