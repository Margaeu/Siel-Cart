<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * The panel leaves transactions off, but a product save writes the
     * product, its variants, and its image rows in separate statements; they
     * must commit or roll back together. Scoped to this page only.
     *
     * Overridden as a method, not by redeclaring $hasDatabaseTransactions:
     * CanUseDatabaseTransactions declares that property as `?bool = null`,
     * and redeclaring a trait property with a different default is a fatal
     * error when the class loads.
     */
    public function hasDatabaseTransactions(): bool
    {
        return true;
    }
}
