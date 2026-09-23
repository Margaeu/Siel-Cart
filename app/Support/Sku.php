<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Two deliberately separate operations on a SKU.
 *
 * sanitizeForStorage() is what gets written: trimmed, but in the case the
 * admin typed it, so saving a product never rewrites an existing SKU's
 * letters. comparisonKey() is only for asking "is this the same SKU?" --
 * uniqueness validation, duplicate rows in one form, and the audit -- and is
 * never persisted. Mixing the two would either lowercase stored SKUs or let
 * "ABC-001" and "abc-001" coexist on sqlite, whose unique index is
 * case-sensitive (MySQL's collation is not).
 */
final class Sku
{
    public static function sanitizeForStorage(?string $value, bool $blankToNull = false): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $blankToNull && $value === '' ? null : $value;
    }

    public static function comparisonKey(?string $value): ?string
    {
        $value = self::sanitizeForStorage($value, blankToNull: true);

        return $value === null ? null : Str::lower($value);
    }
}
