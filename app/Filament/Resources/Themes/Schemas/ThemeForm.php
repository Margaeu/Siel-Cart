<?php

namespace App\Filament\Resources\Themes\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ThemeForm
{
    /**
     * Six-digit hex, nothing else.
     *
     * ColorPicker is a plain text input with a JS panel bolted on — ->format('hex')
     * only picks which web component the panel renders, and the field adds no
     * validation rule of its own. Without this, whatever is typed is stored and
     * then printed verbatim into a <style> block by
     * resources/views/partials/theme-styles.blade.php. Two things went wrong there:
     *
     *  - Blade's {{ }} escapes `<` but not `;` or `}`, so
     *    "red; } html { display: none !important } x{" closed the :root rule early
     *    and switched the whole storefront off. The theme form was a working CSS
     *    injection point.
     *  - A nonsense value like "banana" produced an invalid declaration, so every
     *    var(--color-primary) on the site silently resolved to nothing.
     *
     * Three-digit shorthand is rejected rather than accepted-and-expanded because
     * Filament's Color::hex() reads it with sscanf('#%02x%02x%02x'), taking "ff"
     * and "f" — "#fff" gave the panel oklch(... 0.169 29.714), an orange, while the
     * storefront printed #fff and rendered white. Requiring six digits keeps the
     * panel and the storefront showing the same colour, and matches what the
     * hex-color-picker panel emits anyway.
     */
    private const HEX_RULES = ['regex:/^#[0-9A-Fa-f]{6}$/'];

    private const HEX_MESSAGES = [
        'regex' => 'Enter a 6-digit hex colour such as #1E6031. Shorthand like #fff and named colours like "red" are not accepted.',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Theme')
                    ->description('Name this storefront style and choose whether it is currently in use.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Theme name')
                            ->required()
                            ->maxLength(100)
                            ->helperText('e.g. "Default Green", "Christmas Promo".'),

                        Toggle::make('is_active')
                            ->label('Make this the active theme')
                            ->helperText('Only one theme can be active. Turning this on switches every other theme off.')
                            ->default(false),
                    ])->columns(2),

                Section::make('Colors')
                    ->description('Choose the main institutional and supporting accent colors used across the storefront.')
                    ->schema([
                        ColorPicker::make('primary_color')
                            ->label('Primary color')
                            ->required()
                            ->rules(self::HEX_RULES)
                            ->validationMessages(self::HEX_MESSAGES)
                            ->default('#557F13'),

                        ColorPicker::make('secondary_color')
                            ->label('Secondary color')
                            ->required()
                            ->rules(self::HEX_RULES)
                            ->validationMessages(self::HEX_MESSAGES)
                            ->default('#FFD801'),
                    ])->columns(2),
            ]);
    }
}
