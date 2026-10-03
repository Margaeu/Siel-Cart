<?php

namespace App\Filament\Resources\Themes\Pages;

use App\Filament\Resources\Themes\ThemeResource;
use App\Models\Theme;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTheme extends EditRecord
{
    protected static string $resource = ThemeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The active theme can't be deleted. Deleting it used to succeed and
            // silently drop the storefront and the admin panel back to the
            // built-in CLSU palette (AdminPanelProvider, theme-styles.blade.php),
            // which reads as a broken site. Activating another theme first makes
            // the change deliberate. hidden() is re-evaluated when the action is
            // called, so a stale page can't delete it either.
            DeleteAction::make()
                ->hidden(fn (Theme $record): bool => $record->is_active)
                ->modalHeading(fn (Theme $record): string => "Delete the \"{$record->name}\" theme?")
                ->modalDescription('The theme and its colours and fonts are removed for good and cannot be restored. The storefront is not affected, since this theme is not the active one.'),
        ];
    }
}
