<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    /**
     * Clear the attempted credentials and show one error above the form.
     */
    protected function throwFailureValidationException(): never
    {
        $this->form->fill();

        throw ValidationException::withMessages([
            'login' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.auth.login-error')
                ->visible(fn (): bool => $this->getErrorBag()->has('login'))
                ->viewData(fn (): array => ['message' => $this->getErrorBag()->first('login')]),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getRememberFormComponent(),
        ]);
    }

    protected function getEmailFormComponent(): Component
    {
        /** @var TextInput $component */
        $component = parent::getEmailFormComponent();

        return $component->extraAttributes(fn (): array => $this->getErrorBag()->has('login')
            ? ['class' => 'fi-invalid']
            : []);
    }

    protected function getPasswordFormComponent(): Component
    {
        /** @var TextInput $component */
        $component = parent::getPasswordFormComponent();

        return $component->extraAttributes(fn (): array => $this->getErrorBag()->has('login')
            ? ['class' => 'fi-invalid']
            : []);
    }
}
