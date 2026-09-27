<?php

namespace App\Rules;

use App\Models\Category;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A second "Gift Set" splits one collection into two storefront filters,
 * each holding half the products. Categories are matched on the slug their
 * name generates (see Category::findByGeneratedSlug()), so "Gift Set",
 * "gift  SET!" and "gift-set" are all the same category.
 *
 * The admin category pages do this check in RejectsDuplicateCategory, which
 * is bound to a Livewire page and cannot run inside the product form's
 * inline "create category" modal -- this rule is how that path gets it. Both
 * ask the model the same question, so they cannot disagree.
 */
class UniqueCategoryName implements ValidationRule
{
    public function __construct(
        private readonly ?int $ignoreCategoryId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $existing = Category::findByGeneratedSlug(
            is_scalar($value) ? (string) $value : '',
            $this->ignoreCategoryId,
        );

        if ($existing === null) {
            return;
        }

        $fail(self::messageFor($existing->name));
    }

    public static function messageFor(string $existingName): string
    {
        return "The category \"{$existingName}\" already exists. Choose a different name.";
    }
}
