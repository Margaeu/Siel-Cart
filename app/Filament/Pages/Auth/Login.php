<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

class Login extends BaseLogin
{
    /**
     * On a failed sign-in, clear what the admin typed and keep the error
     * message, so the form looks fresh but still explains what went wrong.
     */
    protected function throwFailureValidationException(): never
    {
        $this->form->fill();

        parent::throwFailureValidationException();
    }

    protected function getPasswordFormComponent(): Component
    {
        /** @var TextInput $component */
        $component = parent::getPasswordFormComponent();

        // The failure message is only attached to the email field, so mark the
        // password input as invalid too whenever that message is present.
        return $component->extraAttributes(fn (): array => $this->getErrorBag()->has('data.email')
            ? ['class' => 'fi-invalid']
            : []);
    }
}
