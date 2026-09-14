<?php

namespace App\Filament\Resources\Reports\Schemas;

use App\Models\Report;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // What was reported, and by whom, is the customer's own input and
                // isn't editable here — only the resolution status changes.
                Section::make('Report')
                    ->description('Submitted by a customer from a product review. Shown here for context and cannot be edited.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Placeholder::make('reporter_name')
                            ->label('Reported by')
                            ->content(fn (?Report $record): string => $record?->reporter?->name ?? '—'),

                        Placeholder::make('reported_name')
                            ->label('Reported user')
                            ->content(fn (?Report $record): string => $record?->reportedCustomer?->name ?? '—'),

                        Placeholder::make('reason')
                            ->label('Reason')
                            ->content(fn (?Report $record): string => $record?->reason ?? '—'),

                        Placeholder::make('submitted_at')
                            ->label('Submitted')
                            ->content(fn (?Report $record): string => $record?->created_at?->format('M d, Y h:i A') ?? '—'),

                        Placeholder::make('details')
                            ->label('Additional details')
                            ->columnSpanFull()
                            ->content(fn (?Report $record): string => $record?->details ?: 'No additional details provided'),

                        Placeholder::make('review_product')
                            ->label('Reported review')
                            ->columnSpanFull()
                            ->content(function (?Report $record): string {
                                $review = $record?->review;

                                if (! $review) {
                                    return 'The original review has been removed.';
                                }

                                $stars = str_repeat('★', $review->rating).str_repeat('☆', 5 - $review->rating);

                                return "On \"{$review->product?->name}\" ({$stars}): "
                                    .($review->comment ?: 'No comment');
                            }),
                    ]),

                Section::make('Resolution')
                    ->description('Mark this report as reviewed once you have taken action, or dismiss it if no action is needed.')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'reviewed' => 'Reviewed (action taken)',
                                'dismissed' => 'Dismissed',
                            ])
                            ->native(false)
                            ->required(),
                    ]),
            ]);
    }
}