<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Reviews\ReviewResource;
use App\Models\Review;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only: moderation stays in ReviewResource. Reviews, their photos and
 * video are retained when the author deletes their account.
 */
class ReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';

    protected static ?string $title = 'Reviews';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('product'))
            ->recordTitleAttribute('title')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->limit(30),
                TextColumn::make('rating')
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state)),
                TextColumn::make('comment')
                    ->limit(50)
                    ->placeholder('No comment'),
                TextColumn::make('media')
                    ->label('Media')
                    ->state(fn (Review $record): string => trim(
                        count($record->photos ?? []).' photo(s)'.($record->video_path ? ', 1 video' : '')
                    )),
                IconColumn::make('is_approved')
                    ->label('Visible')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->date('M d, Y')
                    ->sortable(),
            ])
            ->recordUrl(fn (Review $record): string => ReviewResource::getUrl('edit', ['record' => $record]));
    }
}
