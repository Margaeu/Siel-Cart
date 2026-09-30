<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Pages\Auth\Concerns\HasAuthLayout;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    use HasAuthLayout;

    public function getHeading(): string|Htmlable|null
    {
        return filled($this->userUndertakingMultiFactorAuthentication) ? parent::getHeading() : 'Welcome back, Admin';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return filled($this->userUndertakingMultiFactorAuthentication)
            ? parent::getSubheading()
            : 'Sign in to manage the CLSU Campus Store.';
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()->icon(Heroicon::ArrowRight)->iconPosition('after');
    }

    /**
     * Keep Filament's five-attempt limit, but extend its window to five minutes.
     */
    protected function rateLimit($maxAttempts, $decaySeconds = 300, $method = null, $component = null)
    {
        parent::rateLimit($maxAttempts, $decaySeconds, $method, $component);
    }

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
        $component = $this->authField(parent::getEmailFormComponent(), 'Email Address', Heroicon::OutlinedEnvelope);

        return $component->extraAttributes(fn (): array => $this->getErrorBag()->has('login')
            ? ['class' => 'fi-invalid']
            : []);
    }

    protected function getPasswordFormComponent(): Component
    {
        /** @var TextInput $component */
        $component = $this->authField(parent::getPasswordFormComponent(), 'Password', Heroicon::OutlinedLockClosed);

        // Keep password recovery in the keyboard tab order as well as touch-accessible.
        $component->hint(filament()->hasPasswordReset()
            ? new HtmlString(Blade::render('<x-filament::link :href="filament()->getRequestPasswordResetUrl()">Forgot password?</x-filament::link>'))
            : null);

        return $component->extraAttributes(fn (): array => $this->getErrorBag()->has('login')
            ? ['class' => 'fi-invalid']
            : []);
    }
}
