<?php

namespace App\Filament\Resources\Reviews\Schemas;

use App\Models\Review;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
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
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Placeholder::make('product_name')
                            ->label('Product')
                            ->content(fn (?Review $record): string => $record?->product?->name ?? '—'),

                        Placeholder::make('customer_name')
                            ->label('Customer')
                            ->content(fn (?Review $record): string => $record?->customer?->name ?? '—'),

                        Placeholder::make('order_number')
                            ->label('Order')
                            ->content(fn (?Review $record): string => $record?->order?->order_number ?? 'No linked order'),

                        Placeholder::make('rating_stars')
                            ->label('Rating')
                            ->content(function (?Review $record): string {
                                if (! $record) {
                                    return '—';
                                }

                                return str_repeat('★', $record->rating)
                                    .str_repeat('☆', 5 - $record->rating)
                                    ." ({$record->rating}/5)";
                            }),

                        Placeholder::make('review_title')
                            ->label('Title')
                            ->columnSpanFull()
                            ->content(fn (?Review $record): string => $record?->title ?: 'No title'),

                        Placeholder::make('review_comment')
                            ->label('Comment')
                            ->columnSpanFull()
                            ->content(fn (?Review $record): string => $record?->comment ?: 'No comment'),

                        Placeholder::make('submitted_at')
                            ->label('Submitted')
                            ->content(fn (?Review $record): string => $record?->created_at?->format('M d, Y h:i A') ?? '—'),

                        Placeholder::make('verified_purchase')
                            ->label('Verified purchase')
                            // Set by the storefront when a completed order is found; never edited here.
                            ->content(fn (?Review $record): string => $record?->is_verified_purchase ? 'Yes' : 'No'),
                    ]),

                Section::make('Moderation')
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('is_approved')
                            ->label('Approved')
                            ->helperText('Approved reviews are visible on the product page and counted in its average rating.')
                            ->inline(false),
                    ]),
            ]);
    }
}
