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
            // Product::restoring() makes the restored product inactive and
            // unfeatured; the modal says so, since otherwise it looks like the
            // restore failed to put it back on the storefront.
            // The form was filled before the restore, so refresh both status
            // toggles to keep a later save from undoing the restoring hook.
            RestoreAction::make()
                ->modalDescription('The product will be restored as inactive and no longer featured. Activate or feature it from the edit form when it is ready.')
                ->after(fn () => $this->refreshFormData(['is_active', 'is_featured'])),
            // A soft delete: the product goes to the Trash tab with its
            // images, variants, SKU and slug intact, and can be restored.
            // "Delete" read as permanent, which is what Force delete does.
            DeleteAction::make()
                ->label('Move to trash')
                ->modalHeading('Move product to trash')
                ->modalDescription('The product is hidden from the storefront and moved to the Trash tab. You can restore it later.')
                ->modalSubmitActionLabel('Move to trash')
                ->successNotificationTitle('Moved to trash'),
            ForceDeleteAction::make(),
        ];
    }
}
