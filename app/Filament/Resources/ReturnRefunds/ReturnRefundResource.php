<?php

namespace App\Filament\Resources\ReturnRefunds;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\ReturnRefunds\Pages\CreateReturnRefund;
use App\Filament\Resources\ReturnRefunds\Pages\ListReturnRefunds;
use App\Filament\Resources\ReturnRefunds\Pages\ViewReturnRefund;
use App\Filament\Resources\ReturnRefunds\Schemas\ReturnRefundForm;
use App\Filament\Resources\ReturnRefunds\Schemas\ReturnRefundInfolist;
use App\Filament\Resources\ReturnRefunds\Tables\ReturnRefundsTable;
use App\Models\OrderItemResolution;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Refunds and exchanges UBAP has already carried out, each recorded against
 * the order line it was for.
 *
 * Every record is an OrderItemResolution. They are created only through
 * OrderItemResolutionService (see CreateReturnRefund) and are never changed
 * afterwards, so there is no edit page and no delete action, and
 * OrderItemResolutionPolicy refuses those abilities too.
 */
class ReturnRefundResource extends Resource
{
    protected static ?string $model = OrderItemResolution::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static string|UnitEnum|null $navigationGroup = 'Shop Management';

    protected static ?string $navigationLabel = 'Returns & Refunds';

    protected static ?string $modelLabel = 'refund or exchange';

    protected static ?string $pluralModelLabel = 'returns & refunds';

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return ReturnRefundForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReturnRefundInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReturnRefundsTable::configure($table);
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
            'index' => ListReturnRefunds::route('/'),
            'create' => CreateReturnRefund::route('/create'),
            'view' => ViewReturnRefund::route('/{record}'),
        ];
    }

    /**
     * A resolution outlives a soft-deleted order or customer, and its history
     * should still say whose it was.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'orderItem.order' => fn ($query) => $query->withTrashed(),
            'orderItem.order.customer' => fn ($query) => $query->withTrashed(),
            'processedBy',
        ]);
    }

    /** e.g. "Refund · ORD-1A2B3C4D" */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if (! $record instanceof OrderItemResolution) {
            return parent::getRecordTitle($record);
        }

        return collect([$record->type->getLabel(), $record->orderItem?->order?->order_number])->filter()->implode(' · ');
    }

    /** The order a resolution belongs to, for anyone allowed to open it. */
    public static function getOrderUrl(OrderItemResolution $record): ?string
    {
        $order = $record->orderItem?->order;

        return $order && OrderResource::canView($order)
            ? OrderResource::getUrl('view', ['record' => $order])
            : null;
    }
}
