<?php

namespace App\Filament\Resources\Reviews\Tables;

use App\Models\Review;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // customer.name is an accessor, so the relations must be loaded up front.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product', 'customer']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    // first_name/last_name are the real columns behind the accessor.
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'customer',
                        fn (Builder $q) => $q
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                    )),

                Tables\Columns\TextColumn::make('rating')
                    ->label('Rating')
                    ->sortable()
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->color(fn (int $state): string => match (true) {
                        $state >= 4 => 'success',
                        $state === 3 => 'warning',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->placeholder('No title')
                    ->limit(35)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('comment')
                    ->label('Comment')
                    ->limit(50)
                    ->tooltip(fn (Review $record): ?string => $record->comment)
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_verified_purchase')
                    ->label('Verified')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('is_approved')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state ? 'Approved' : 'Pending')
                    ->color(fn (mixed $state): string => $state ? 'success' : 'warning')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('M d, Y h:i A')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_approved')
                    ->label('Approval status')
                    ->placeholder('All reviews')
                    ->trueLabel('Approved only')
                    ->falseLabel('Pending only')
                    ->default(false),

                SelectFilter::make('rating')
                    ->label('Rating')
                    ->options([
                        5 => '5 stars',
                        4 => '4 stars',
                        3 => '3 stars',
                        2 => '2 stars',
                        1 => '1 star',
                    ])
                    ->native(false),

                TernaryFilter::make('is_verified_purchase')
                    ->label('Verified purchase')
                    ->placeholder('All reviews')
                    ->trueLabel('Verified only')
                    ->falseLabel('Unverified only'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('This review will become visible on the product page.')
                    ->action(function (Review $record) {
                        $record->update(['is_approved' => true]);

                        Notification::make()
                            ->title('Review approved')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Review $record): bool => ! $record->is_approved),

                Action::make('unapprove')
                    ->label('Unapprove')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('This review will be hidden from the product page.')
                    ->action(function (Review $record) {
                        $record->update(['is_approved' => false]);

                        Notification::make()
                            ->title('Review unapproved')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Review $record): bool => $record->is_approved),

                //EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label('Approve selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $count = Review::whereKey($records->modelKeys())
                                ->update(['is_approved' => true]);

                            Notification::make()
                                ->title("{$count} review(s) approved")
                                ->success()
                                ->send();
                        }),

                    BulkAction::make('unapprove')
                        ->label('Unapprove selected')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $count = Review::whereKey($records->modelKeys())
                                ->update(['is_approved' => false]);

                            Notification::make()
                                ->title("{$count} review(s) unapproved")
                                ->success()
                                ->send();
                        }),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No reviews')
            ->emptyStateDescription('Customer reviews will appear here once they are submitted.');
    }
}
