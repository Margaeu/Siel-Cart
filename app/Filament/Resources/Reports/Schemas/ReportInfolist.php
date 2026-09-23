<?php

namespace App\Filament\Resources\Reports\Schemas;

use App\Models\Report;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Report')
                    ->description('Submitted by a customer from a product review.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('reporter.name')
                            ->label('Reported by')
                            ->placeholder('—'),

                        TextEntry::make('reportedCustomer.name')
                            ->label('Reported user')
                            ->placeholder('—'),

                        TextEntry::make('reason')
                            ->label('Reason'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => ucfirst($state))
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'reviewed' => 'success',
                                'dismissed' => 'gray',
                                default => 'gray',
                            }),

                        TextEntry::make('created_at')
                            ->label('Submitted')
                            ->dateTime('M d, Y h:i A'),

                        TextEntry::make('details')
                            ->label('Additional details')
                            ->placeholder('No additional details provided')
                            ->columnSpanFull(),
                    ]),

                // The report's own reason/details are the reporter's complaint;
                // this section is the actual content being complained about, read
                // straight off the Review model so admins don't have to leave the
                // report to judge it.
                Section::make('Reported review')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('review.rating')
                            ->label('Rating')
                            ->formatStateUsing(fn (?int $state): string => $state === null
                                ? '—'
                                : str_repeat('★', $state).str_repeat('☆', 5 - $state)." ({$state}/5)")
                            ->color(fn (?int $state): string => match (true) {
                                $state === null => 'gray',
                                $state >= 4 => 'success',
                                $state === 3 => 'warning',
                                default => 'danger',
                            })
                            ->size('lg'),

                        IconEntry::make('review.is_verified_purchase')
                            ->label('Verified purchase')
                            ->boolean(),

                        TextEntry::make('review.title')
                            ->label('Title')
                            ->placeholder('No title')
                            ->weight('bold')
                            ->columnSpanFull(),

                        TextEntry::make('review.comment')
                            ->label('Description')
                            ->placeholder('No comment')
                            ->extraAttributes(['class' => 'whitespace-pre-line'])
                            ->columnSpanFull(),

                        TextEntry::make('review.product.name')
                            ->label('Product')
                            ->placeholder('Product removed'),

                        // review() is withTrashed, so a deleted reviewer still
                        // resolves through Customer::getNameAttribute.
                        TextEntry::make('review.customer.name')
                            ->label('Review author')
                            ->placeholder('—'),

                        TextEntry::make('review.created_at')
                            ->label('Review submitted')
                            ->dateTime('M d, Y h:i A')
                            ->placeholder('—'),
                    ])
                    ->visible(fn (Report $record): bool => $record->review !== null),

                Section::make('Photos & video')
                    ->description('Media attached to the reported review.')
                    ->columnSpanFull()
                    ->schema([
                        ViewEntry::make('media')
                            ->hiddenLabel()
                            ->view('filament.reports.reported-review-media'),
                    ])
                    ->visible(fn (Report $record): bool => $record->review !== null),

                Section::make('Reported review')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('review_removed')
                            ->hiddenLabel()
                            ->state('The original review has been removed and can no longer be shown here.'),
                    ])
                    ->visible(fn (Report $record): bool => $record->review === null),
            ]);
    }
}
