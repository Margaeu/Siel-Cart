<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;
    protected static string|UnitEnum|null $navigationGroup = 'Content Management';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Site Branding';
    protected static ?string $title = 'Site Branding';

    protected string $view = 'filament.pages.manage-site-settings';

    /**
     * Keys this page is allowed to read/write in the settings table.
     * Keep this list in sync with the fields defined in form().
     *
     * Colors and fonts moved to the Color Themes & Fonts resource — see
     * App\Models\Theme — so they're no longer managed here.
     */
    private const MANAGED_KEYS = [
        'site_name',
        'tagline',
        'logo_path',
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
        ], array_filter($current, fn ($value) => $value !== null)));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Branding')
                    ->description('Shown in the storefront header, footer, and page titles. For colors and fonts, see Design → Color Themes & Fonts.')
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
