<?php

namespace App\Filament\Resources\Reviews\Tables;

use App\Models\Review;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
            // 60s, not faster: see the note on OrdersTable's ->poll().
            ->poll('60s')
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
                    // `name` is an accessor, not a column; search the real ones.
                    // nameLike() groups its OR, which the old inline
                    // where/orWhere did not: ungrouped, the OR escaped the
                    // whereHas correlation and matched every review.
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'customer',
                        fn (Builder $q) => $q->nameLike($search),
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

                // Reviews go live the moment a customer submits them
                // (ProductDetails sets is_approved = true), so there is no
                // "pending" state: an admin only ever hides or re-shows one.
                Tables\Columns\TextColumn::make('is_approved')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state ? 'Visible' : 'Hidden')
                    ->color(fn (mixed $state): string => $state ? 'success' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('M d, Y h:i A')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_approved')
                    ->label('Visibility')
                    ->placeholder('All reviews')
                    ->trueLabel('Visible only')
                    ->falseLabel('Hidden only'),

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

                SelectFilter::make('is_verified_purchase')
                    ->label('Verified purchase')
                    ->placeholder('All reviews')
                    ->options([
                        1 => 'Verified only',
                    ]),
            ])
            ->recordActions([
                Action::make('show')
                    ->label('Show')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('This review will become visible on the product page again.')
                    ->action(function (Review $record) {
                        $record->update(['is_approved' => true]);

                        Notification::make()
                            ->title('Review is now visible')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Review $record): bool => ! $record->is_approved),

                Action::make('hide')
                    ->label('Hide')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('This review will be hidden from the product page and left out of its average rating.')
                    ->action(function (Review $record) {
                        $record->update(['is_approved' => false]);

                        Notification::make()
                            ->title('Review hidden')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Review $record): bool => $record->is_approved),

                ViewAction::make(),
                // EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('show')
                        ->label('Show selected')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $count = Review::whereKey($records->modelKeys())
                                ->update(['is_approved' => true]);

                            Notification::make()
                                ->title("{$count} review(s) now visible")
                                ->success()
                                ->send();
                        }),

                    BulkAction::make('hide')
                        ->label('Hide selected')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $count = Review::whereKey($records->modelKeys())
                                ->update(['is_approved' => false]);

                            Notification::make()
                                ->title("{$count} review(s) hidden")
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
