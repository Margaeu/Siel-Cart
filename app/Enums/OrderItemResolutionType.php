<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What UBAP did about an order line after the sale.
 */
enum OrderItemResolutionType: string implements HasColor, HasLabel
{
    case Refund = 'refund';
    case Exchange = 'exchange';

    public function getLabel(): string
    {
        return match ($this) {
            self::Refund => 'Refund',
            self::Exchange => 'Exchange',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Refund => 'warning',
            self::Exchange => 'info',
        };
    }

    /** How the outcome reads against an order line. */
    public function getOutcomeLabel(): string
    {
        return match ($this) {
            self::Refund => 'Refunded',
            self::Exchange => 'Exchanged',
        };
    }
}
