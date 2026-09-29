<?php

namespace App\Filament\Resources\Reviews\Schemas;

use App\Models\Review;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReviewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Review')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('rating')
                            ->label('Rating')
                            ->formatStateUsing(fn (int $state): string => str_repeat('★', $state)
                                .str_repeat('☆', 5 - $state)
                                ." ({$state}/5)")
                            ->color(fn (int $state): string => match (true) {
                                $state >= 4 => 'success',
                                $state === 3 => 'warning',
                                default => 'danger',
                            })
                            ->size('lg'),

                        TextEntry::make('is_approved')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): string => $state ? 'Visible' : 'Hidden')
                            ->color(fn (mixed $state): string => $state ? 'success' : 'gray'),

                        TextEntry::make('title')
                            ->label('Title')
                            ->placeholder('No title')
                            ->weight('bold')
                            ->columnSpanFull(),

                        // Plain text from the storefront textarea; keep the
                        // customer's line breaks without rendering any HTML.
                        TextEntry::make('comment')
                            ->label('Description')
                            ->placeholder('No comment')
                            ->extraAttributes(['class' => 'whitespace-pre-line'])
                            ->columnSpanFull(),
                    ]),

                // Photos and video are stored as R2 paths; the view reads them
                // through Review::photo_urls / video_url rather than building URLs.
                Section::make('Photos & video')
                    ->description('Media the customer attached to this review.')
                    ->columnSpanFull()
                    ->schema([
                        ViewEntry::make('media')
                            ->hiddenLabel()
                            ->view('filament.reviews.media'),
                    ]),

                Section::make('Details')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('product.name')
                            ->label('Product')
                            ->placeholder('Product removed'),

                        // customer() is withTrashed, so a deleted account reads
                        // "Deleted customer" through the name accessor.
                        TextEntry::make('customer.name')
                            ->label('Customer')
                            ->placeholder('—'),

                        TextEntry::make('order.order_number')
                            ->label('Order')
                            ->placeholder('No linked order'),

                        IconEntry::make('is_verified_purchase')
                            ->label('Verified purchase')
                            ->boolean(),

                        TextEntry::make('created_at')
                            ->label('Submitted')
                            ->dateTime('M d, Y h:i A'),
                    ]),
            ]);
    }
}
