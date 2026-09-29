<?php

namespace App\Filament\Resources\Reviews\Schemas;

use App\Models\Review;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // The review itself is the customer's own words and is derived from a
                // completed order, so everything here is read-only. Moderation is the
                // only thing an admin changes; unwanted reviews are deleted, not rewritten.
                Section::make('Review')
                    ->description('Customer-submitted content is shown here for context and cannot be edited.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('product_name')
                            ->label('Product')
                            ->content(fn (?Review $record): string => $record?->product?->name ?? '—'),

                        TextEntry::make('customer_name')
                            ->label('Customer')
                            ->content(fn (?Review $record): string => $record?->customer?->name ?? '—'),

                        TextEntry::make('order_number')
                            ->label('Order')
                            ->content(fn (?Review $record): string => $record?->order?->order_number ?? 'No linked order'),

                        TextEntry::make('rating_stars')
                            ->label('Rating')
                            ->content(function (?Review $record): string {
                                if (! $record) {
                                    return '—';
                                }

                                return str_repeat('★', $record->rating)
                                    .str_repeat('☆', 5 - $record->rating)
                                    ." ({$record->rating}/5)";
                            }),

                        TextEntry::make('review_title')
                            ->label('Title')
                            ->columnSpanFull()
                            ->content(fn (?Review $record): string => $record?->title ?: 'No title'),

                        TextEntry::make('review_comment')
                            ->label('Comment')
                            ->columnSpanFull()
                            ->content(fn (?Review $record): string => $record?->comment ?: 'No comment'),

                        TextEntry::make('submitted_at')
                            ->label('Submitted')
                            ->content(fn (?Review $record): string => $record?->created_at?->format('M d, Y h:i A') ?? '—'),

                        TextEntry::make('verified_purchase')
                            ->label('Verified purchase')
                            // Set by the storefront when a completed order is found; never edited here.
                            ->content(fn (?Review $record): string => $record?->is_verified_purchase ? 'Yes' : 'No'),
                    ]),

                Section::make('Moderation')
                    ->description('Reviews are published as soon as they are submitted. Hide one to remove it from the product page.')
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('is_approved')
                            ->label('Visible')
                            ->helperText('Visible reviews appear on the product page and count toward its average rating.')
                            ->inline(false),
                    ]),
            ]);
    }
}
