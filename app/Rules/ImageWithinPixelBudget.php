<?php

namespace App\Rules;

use App\Support\OptimizedImageStorage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Refuses an image with more pixels than GD can safely decode.
 *
 * The upload fields cap file size, but file size says almost nothing about
 * what a decode costs: an 8000x6000 photo compresses to about 1.2 MB and
 * still wants ~202 MB of bitmap, against a 256 MB memory_limit. Exceeding
 * that is a fatal error rather than an exception, so nothing downstream can
 * catch it -- the PHP-FPM worker simply dies and the admin is left looking at
 * a dead page. See OptimizedImageStorage::MAX_PIXELS for the measurements.
 *
 * This is the layer that can actually say so. OptimizedImageStorage refuses to
 * decode an oversized image too, but its contract is never to block an upload,
 * so on its own it would quietly store the original at full resolution and
 * serve it to every visitor.
 *
 * Dimensions are read from the file header (no bitmap is allocated), so
 * checking is safe on exactly the files that would otherwise be fatal.
 */
class ImageWithinPixelBudget implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $path = $value->getRealPath();

        if ($path === false || ! is_readable($path)) {
            return;
        }

        $size = @getimagesize($path);

        // Not something with readable dimensions -- a clip, an SVG, or a file
        // another rule will reject. Only a known-oversized image fails here;
        // this rule never guesses.
        if ($size === false) {
            return;
        }

        $pixels = (int) ($size[0] ?? 0) * (int) ($size[1] ?? 0);

        if ($pixels <= OptimizedImageStorage::MAX_PIXELS) {
            return;
        }

        $fail(sprintf(
            'This image is %s megapixels (%d×%d), which is too large to process. The limit is %s megapixels — resize it (about %d pixels wide is plenty) and upload it again.',
            $this->megapixels($pixels),
            (int) $size[0],
            (int) $size[1],
            $this->megapixels(OptimizedImageStorage::MAX_PIXELS),
            1920,
        ));
    }

    private function megapixels(int $pixels): string
    {
        return rtrim(rtrim(number_format($pixels / 1_000_000, 1), '0'), '.');
    }
}
