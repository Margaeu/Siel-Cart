<?php

namespace App\Filament\Resources\Reviews\Pages;

use App\Filament\Resources\Reviews\ReviewResource;
use App\Models\Review;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * Read-only view of a review, including the photos and video the customer
 * attached, so an admin can see everything before hiding it. Reviews are
 * published as soon as they are submitted, so moderation is hide/show and
 * mirrors the row actions in ReviewsTable.
 */
class ViewReview extends ViewRecord
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
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

            DeleteAction::make(),
        ];
    }
}
