<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The same two operations App\Support\Sku performs, for the display names
 * customers read: a product's name and a variant's name.
 *
 * sanitizeForStorage() is what gets written -- trimmed, with runs of
 * whitespace collapsed, in the case the admin typed it -- so "Medium" is
 * never stored as " Medium " or "Extra  Large" as "Extra  Large".
 * Normalizing on the way in is what lets the uniqueness rules compare with
 * plain LOWER(TRIM(name)) SQL that works on MySQL and sqlite alike; neither
 * has a portable way to collapse an inner double space.
 *
 * comparisonKey() only answers "is this the same name?" -- uniqueness
 * validation and duplicate rows in one form -- and is never persisted.
 * Lowercasing on the way in instead would rewrite every admin's
 * capitalization, and the name is what the storefront prints.
 */
final class Name
{
    public static function sanitizeForStorage(?string $value, bool $blankToNull = false): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(Str::squish($value));

        return $blankToNull && $value === '' ? null : $value;
    }

    public static function comparisonKey(?string $value): ?string
    {
        $value = self::sanitizeForStorage($value, blankToNull: true);

        return $value === null ? null : Str::lower($value);
    }
}
