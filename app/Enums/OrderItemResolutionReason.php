<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The reasons UBAP accepts a return for. Change of mind is not one of them.
 */
enum OrderItemResolutionReason: string implements HasLabel
{
    case Defective = 'defective';
    case Damaged = 'damaged';
    case SellerError = 'seller_error';

    public function getLabel(): string
    {
        return match ($this) {
            self::Defective => 'Defective',
            self::Damaged => 'Damaged',
            self::SellerError => 'Seller error',
        };
    }
}
