<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * The product update, repeater variant creates/updates/deletes (and the
     * image rows those deletes take with them), and gallery row changes must
     * commit or roll back together -- ProductImage only removes R2 objects
     * after commit, so a rolled-back save keeps every file. Scoped to this
     * page; the panel default stays off. A method override rather than a
     * redeclared $hasDatabaseTransactions: see CreateProduct.
     */
    public function hasDatabaseTransactions(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            RestoreAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
        ];
    }
}
