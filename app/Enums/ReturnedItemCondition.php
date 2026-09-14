<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The state an item UBAP released in error is in when it comes back.
 */
enum ReturnedItemCondition: string implements HasLabel
{
    case Sellable = 'sellable';
    case Damaged = 'damaged';
    case Defective = 'defective';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sellable => 'Sellable',
            self::Damaged => 'Damaged',
            self::Defective => 'Defective',
        };
    }

    /** Whether it can go back on the shelf. */
    public function isSellable(): bool
    {
        return $this === self::Sellable;
    }
}
