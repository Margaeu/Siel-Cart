<?php

namespace App\Filament\Pages\Auth\Concerns;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;

trait HasAuthLayout
{
    public function getLayout(): string
    {
        return 'filament.auth.layout';
    }

    public function hasLogo(): bool
    {
        return false;
    }

    protected function authField(Component $component, string $placeholder, Heroicon $icon): TextInput
    {
        /** @var TextInput $component */
        return $component
            ->placeholder($placeholder)
            ->prefixIcon($icon)
            ->inlinePrefix()
            ->inlineSuffix();
    }
}
