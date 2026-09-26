<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\ReportResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditReport extends EditRecord
{
    protected static string $resource = ReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // A report is just the complaint ticket — deleting it does NOT
            // remove the review it's about. This gives admins a direct way
            // to act on the actual reported content from the same screen,
            // instead of having to separately find it in the Reviews list.
            Action::make('deleteReview')
                ->label('Delete Reported Review')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Delete the reported review?')
                ->modalDescription('This removes the review itself, with its photos and video, from the product page — not just this report. The customer will not be able to post another review for this purchase.')
                ->visible(fn () => $this->record->review !== null)
                ->action(function () {
                    $this->record->review->delete();
                    $this->record->update(['status' => 'reviewed']);

                    Notification::make()
                        ->title('Review deleted')
                        ->success()
                        ->send();

                    $this->redirect(ReportResource::getUrl('index'));
                }),

            DeleteAction::make()
                ->label('Delete Report Only'),
        ];
    }
}