<?php

namespace App\Filament\Resources\Themes\Tables;

use App\Models\Theme;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ThemesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->weight('bold'),
                ColorColumn::make('primary_color')
                    ->label('Primary'),
                ColorColumn::make('secondary_color')
                    ->label('Secondary'),
                TextColumn::make('is_active')
                    ->label('Active')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (mixed $state): string => $state ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Last updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('activate')
                    ->label('Set Active')
                    ->icon(Heroicon::Check)
                    ->color('success')
                    ->visible(fn (Theme $record) => ! $record->is_active)
                    ->action(function (Theme $record) {
                        $record->update(['is_active' => true]);

                        Notification::make()
                            ->title("\"{$record->name}\" is now the active theme")
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // All or nothing, like EditTheme's delete: a selection that
                    // includes the active theme deletes none of them, rather
                    // than dropping the storefront to the fallback palette.
                    DeleteBulkAction::make()
                        ->modalHeading(fn (Collection $records): string => 'Delete '.$records->count().' '.str('theme')->plural($records->count()).'?')
                        ->modalDescription(fn (Collection $records): string => 'These themes are removed for good and cannot be restored: '.$records->pluck('name')->implode(', ').'.')
                        ->before(function (DeleteBulkAction $action, Collection $records): void {
                            $active = $records->firstWhere('is_active', true);

                            if ($active === null) {
                                return;
                            }

                            Notification::make()
                                ->title('No themes were deleted')
                                ->body("\"{$active->name}\" is the active theme. Activate a different theme first, then delete this one.")
                                ->danger()
                                ->send();

                            $action->cancel();
                        }),
                ]),
            ]);
    }
}
