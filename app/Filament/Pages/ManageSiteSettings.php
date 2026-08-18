<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManageSiteSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Swatch;
    protected static string|UnitEnum|null $navigationGroup = 'Design';

    protected static ?string $navigationLabel = 'Site Branding & Theme';
    protected static ?string $title = 'Site Branding & Theme';

    protected string $view = 'filament.pages.manage-site-settings';

    /**
     * Keys this page is allowed to read/write in the settings table.
     * Keep this list in sync with the fields defined in form().
     */
    private const MANAGED_KEYS = [
        'site_name',
        'tagline',
        'logo_path',
        'primary_color',
        'secondary_color',
        'font_family',
    ];

    public ?array $data = [];

    public function mount(): void
    {
        $current = [];

        foreach (self::MANAGED_KEYS as $key) {
            $current[$key] = Setting::get($key);
        }

        $this->form->fill(array_merge([
            'site_name' => 'CLSU Shop',
            'tagline' => null,
            'logo_path' => null,
            'primary_color' => '#2E7D32',
            'secondary_color' => '#F9A825',
            'font_family' => 'Inter',
        ], array_filter($current, fn ($value) => $value !== null)));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Branding')
                    ->description('Shown in the storefront header, footer, and page titles.')
                    ->schema([
                        TextInput::make('site_name')
                            ->label('Site name')
                            ->required()
                            ->maxLength(100),

                        TextInput::make('tagline')
                            ->maxLength(150),

                        FileUpload::make('logo_path')
                            ->image()
                            ->directory('branding')
                            ->imageEditor()
                            ->maxSize(1024)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Color palette')
                    ->description('Applied storefront-wide as CSS variables.')
                    ->schema([
                        ColorPicker::make('primary_color')
                            ->label('Primary color'),

                        ColorPicker::make('secondary_color')
                            ->label('Secondary color'),
                    ])
                    ->columns(2),

                Section::make('Typography')
                    ->description('Choose from a pre-approved set of web-safe Google Fonts. Custom font uploads and per-section typography are handled in code.')
                    ->schema([
                        Select::make('font_family')
                            ->label('Site font')
                            ->options([
                                'Inter' => 'Inter',
                                'Poppins' => 'Poppins',
                                'Roboto' => 'Roboto',
                                'Nunito' => 'Nunito',
                                'Merriweather' => 'Merriweather',
                                'Playfair Display' => 'Playfair Display',
                            ])
                            ->native(false)
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save changes')
                ->icon(Heroicon::Check)
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach (self::MANAGED_KEYS as $key) {
            Setting::set($key, $state[$key] ?? null, 'string', 'branding');
        }

        Notification::make()
            ->title('Site branding updated')
            ->success()
            ->send();
    }
}
