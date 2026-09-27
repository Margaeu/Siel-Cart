<?php

namespace App\Rules;

use App\Models\Product;
use App\Support\Name;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The product name is what a customer reads on the card, the product page,
 * and every order line, so two products cannot share one -- an admin who
 * re-adds a product that already exists, or fixes a typo by creating a second
 * copy, leaves customers with two identical listings and no way to tell them
 * apart. Nothing caught this before: the slug is made unique on its own by
 * appending "-2", so a duplicate name saved silently.
 *
 * The stored column is wrapped in LOWER(TRIM(...)) so names differing only in
 * case or padding compare equal -- sqlite's comparison is case-sensitive
 * where MySQL's collation is not. Names are stored squished (see
 * App\Support\Name), which is what makes this SQL enough.
 *
 * withTrashed(): a soft-deleted product can be restored from the edit page,
 * and a restore that lands a second "CLSU Tumbler" on the storefront is the
 * same problem arriving later. The admin gets told which case they hit, since
 * a trashed match is not visible in the product list.
 */
class UniqueProductName implements ValidationRule
{
    public const MESSAGE = 'Another product already uses this name.';

    public const TRASHED_MESSAGE = 'A deleted product already uses this name. Restore that product instead, or choose a different name.';

    public function __construct(
        private readonly ?int $ignoreProductId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $key = Name::comparisonKey(is_scalar($value) ? (string) $value : null);

        if ($key === null) {
            return;
        }

        $match = Product::withTrashed()
            ->whereRaw('LOWER(TRIM(name)) = ?', [$key])
            ->when($this->ignoreProductId, fn ($query, $id) => $query->whereKeyNot($id))
            ->first(['id', 'deleted_at']);

        if ($match === null) {
            return;
        }

        $fail($match->trashed() ? self::TRASHED_MESSAGE : self::MESSAGE);
    }
}
