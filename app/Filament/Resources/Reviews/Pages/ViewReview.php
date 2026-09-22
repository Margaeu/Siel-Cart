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
 * attached, so an admin can see everything before approving it. Moderation
 * mirrors the approve/unapprove row actions in ReviewsTable.
 */
class ViewReview extends ViewRecord
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
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

            DeleteAction::make(),
        ];
    }
}
