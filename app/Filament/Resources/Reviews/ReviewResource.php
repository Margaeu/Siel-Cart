<?php

namespace App\Filament\Resources\Reviews;

use App\Filament\Resources\Reviews\Pages\EditReview;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\Reviews\Pages\ViewReview;
use App\Filament\Resources\Reviews\Schemas\ReviewForm;
use App\Filament\Resources\Reviews\Schemas\ReviewInfolist;
use App\Filament\Resources\Reviews\Tables\ReviewsTable;
use App\Models\Review;
use App\Support\AdminNavigationBadges;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;
class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;
    protected static string | UnitEnum | null $navigationGroup = 'Shop Management';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return ReviewForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReviewInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReviewsTable::configure($table);
    }

    // Reviews are the customer's own words, so admins moderate them (view,
    // delete) and never edit them. The table and view page offer no edit
    // action, but the edit route is still registered, and Update:Review is no
    // longer a Shield permission: Filament treats an ability the policy does
    // not define as allowed, so without this any admin who can view reviews
    // could open /edit by URL.
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviews::route('/'),
            'view' => ViewReview::route('/{record}'),
            'edit' => EditReview::route('/{record}/edit'),
        ];
    }

    /**
     * Surface the pending moderation queue in the sidebar.
     */
    public static function getNavigationBadge(): ?string
    {
        return app(AdminNavigationBadges::class)->pending(Review::class);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
