<?php

namespace App\Rules;

use App\Models\Category;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * The slug is generated from the name once, at creation, so the generated
 * value is what has to be checked: a name like "!!!" slugifies to nothing
 * and would store an unreachable storefront filter, and some characters
 * expand ("@" becomes "at"), so the slug column's 255 limit is not the same
 * check as the name's own maxLength.
 *
 * Category::boot() refuses an empty slug too, but by then the only way to
 * report it is an exception thrown mid-save. This keeps it a field error in
 * whichever form is being filled in -- the category pages or the product
 * form's inline "create category" modal.
 */
class CategoryNameMakesASlug implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $slug = Str::slug(is_scalar($value) ? (string) $value : '');

        if ($slug === '') {
            $fail(Category::EMPTY_SLUG_MESSAGE);
        } elseif (mb_strlen($slug) > 255) {
            $fail('The name is too long to make a web address from. Shorten it.');
        }
    }
}
