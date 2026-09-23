<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\ReportResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only view of a report, including the full reported review, so an
 * admin can judge it without leaving the page. Mirrors ReviewResource's
 * ViewReview.
 */
class ViewReport extends ViewRecord
{
    protected static string $resource = ReportResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load(['reporter', 'reportedCustomer', 'review.product', 'review.customer']);
    }

    protected function getHeaderActions(): array
    {
        return [
            // Same shortcut EditReport offers: act on the actual reported
            // content from the review page instead of hunting for it separately.
            Action::make('deleteReview')
                ->label('Delete Reported Review')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Delete the reported review?')
                ->modalDescription('This permanently removes the review itself (and any attached photos/video) from the product page — not just this report.')
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

            EditAction::make(),

            DeleteAction::make()
                ->label('Delete Report Only'),
        ];
    }
}
