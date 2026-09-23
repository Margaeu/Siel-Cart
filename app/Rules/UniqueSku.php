<?php

namespace App\Rules;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Sku;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A SKU identifies one sellable thing, whether it sits on a simple product or
 * on a variant, so it must be unique across both tables. The per-table
 * unique indexes cannot see each other, and are case-sensitive on sqlite.
 *
 * The stored column is wrapped in LOWER(TRIM(...)) so SKUs saved before
 * sanitization existed -- padded or mixed-case -- still compare equal.
 * withTrashed(): the products unique index covers soft-deleted rows too, and
 * a trashed product can be restored with its SKU.
 */
class UniqueSku implements ValidationRule
{
    public const MESSAGE = 'This SKU is already used by another product or variant.';

    public function __construct(
        private readonly ?int $ignoreProductId = null,
        private readonly ?int $ignoreVariantId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $key = Sku::comparisonKey(is_scalar($value) ? (string) $value : null);

        if ($key === null) {
            return;
        }

        $productTaken = Product::withTrashed()
            ->whereRaw('LOWER(TRIM(sku)) = ?', [$key])
            ->when($this->ignoreProductId, fn ($query, $id) => $query->whereKeyNot($id))
            ->exists();

        $variantTaken = $productTaken || ProductVariant::query()
            ->whereRaw('LOWER(TRIM(sku)) = ?', [$key])
            ->when($this->ignoreVariantId, fn ($query, $id) => $query->whereKeyNot($id))
            ->exists();

        if ($productTaken || $variantTaken) {
            $fail(self::MESSAGE);
        }
    }
}
