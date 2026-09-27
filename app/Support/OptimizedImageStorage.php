<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Re-encodes an uploaded image at a capped width before it lands on R2, so an
 * admin's straight-from-camera photo (often 3000px+ and several MB) doesn't
 * ship to every storefront visitor at full resolution. `scaleDown()` never
 * upscales, so a smaller source is stored untouched apart from re-encoding.
 *
 * SVGs, GIFs and video are stored as-is: GD flattens an animated GIF to its
 * first frame, can't touch vector paths, and can't decode a video container at
 * all. Any decode failure also falls back to the original bytes, so a corrupt
 * or unusual file never blocks the upload -- this is a size optimization, not a
 * validation gate.
 *
 * Video is skipped explicitly rather than left to that fallback: GD would throw
 * on every clip a Design Admin uploads to the banner carousel, and the catch
 * below report()s before returning null, so each upload would have filed a
 * bogus exception while appearing to succeed.
 *
 * An image whose pixel count is over MAX_PIXELS is left alone for the same
 * reason -- see the constant. Rejecting an upload is the validation layer's job
 * (App\Rules\ImageWithinPixelBudget, attached to every upload field), because
 * only a field error can actually tell the admin what to do about it.
 */
class OptimizedImageStorage
{
    private const SKIP_EXTENSIONS = ['svg', 'gif', 'mp4'];

    /**
     * Largest image, in pixels, that may be handed to GD.
     *
     * GD decodes to an uncompressed bitmap, so cost tracks pixels, not file
     * size -- and the two are wildly different. Measured on this build
     * (PHP 8.2 / GD), peak memory for read + scaleDown + encode:
     *
     *   24 MP JPEG (6000x4000)  ~112 MB     file was 0.74 MB
     *   24 MP PNG  (6000x4000)  ~154 MB     file was 0.22 MB
     *   48 MP JPEG (8000x6000)  ~202 MB     file was 1.23 MB
     *
     * That last row is why the upload fields' 3 MB size cap is no protection
     * at all: a 1.23 MB file is enough to want 202 MB of bitmap. Production
     * runs memory_limit=256M (public/.user.ini) and a booted Laravel request
     * already holds ~42 MB before Filament and Livewire are counted, so a big
     * enough image exhausts the limit.
     *
     * That failure is a fatal error, NOT an exception -- PHP cannot catch
     * memory exhaustion, so the try/catch in reencode() below does not help.
     * The worker dies mid-request and the admin gets a dead page with no
     * message. On the B1 plan, which has very few PHP-FPM workers, losing one
     * to an upload is a site-wide event rather than a failed form.
     *
     * 16 MP is the ceiling that keeps the worst case (PNG, ~6.7 bytes/pixel)
     * near 108 MB, leaving room for the framework underneath it. Raise this
     * only alongside memory_limit, and re-measure rather than extrapolating.
     */
    public const MAX_PIXELS = 16_000_000;

    private const JPEG_QUALITY = 82;

    private const WEBP_QUALITY = 82;

    public static function store(TemporaryUploadedFile $file, string $disk, string $directory, string $filename, int $maxWidth): string
    {
        $path = trim($directory.'/'.$filename, '/');
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $contents = file_get_contents($file->getRealPath());

        if ($contents !== false && ! in_array($extension, self::SKIP_EXTENSIONS, true)) {
            $contents = self::reencode($contents, $extension, $maxWidth) ?? $contents;
        }

        Storage::disk($disk)->put($path, $contents, 'public');

        return $path;
    }

    /**
     * Pixel count from the file header alone.
     *
     * getimagesizefromstring() parses the header and allocates no bitmap,
     * which is the entire point: it has to be safe to call on the very file
     * that would exhaust memory if decoded. Returns null when the dimensions
     * cannot be read -- video, SVG and anything malformed land here, and the
     * callers all treat null as "not known to be oversized" rather than
     * guessing.
     */
    public static function pixelCountOf(string $contents): ?int
    {
        $size = @getimagesizefromstring($contents);

        if ($size === false) {
            return null;
        }

        $width = (int) ($size[0] ?? 0);
        $height = (int) ($size[1] ?? 0);

        if ($width < 1 || $height < 1) {
            return null;
        }

        return $width * $height;
    }

    public static function exceedsPixelBudget(string $contents): bool
    {
        $pixels = self::pixelCountOf($contents);

        return $pixels !== null && $pixels > self::MAX_PIXELS;
    }

    private static function reencode(string $contents, string $extension, int $maxWidth): ?string
    {
        // Checked before the decode, never after: once GD has been asked to
        // read the image the memory is already gone, and a fatal error cannot
        // be recovered from. Returning null stores the original bytes, keeping
        // this class's promise never to block an upload -- the validation rule
        // is what stops an oversized image getting this far.
        if (self::exceedsPixelBudget($contents)) {
            Log::warning('Skipped image optimization: over the pixel budget.', [
                'pixels' => self::pixelCountOf($contents),
                'max_pixels' => self::MAX_PIXELS,
                'extension' => $extension,
            ]);

            return null;
        }

        try {
            $image = ImageManager::gd()->read($contents);
            $image->scaleDown(width: $maxWidth);

            $encoded = match ($extension) {
                'png' => $image->toPng(),
                'webp' => $image->toWebp(quality: self::WEBP_QUALITY),
                default => $image->toJpeg(quality: self::JPEG_QUALITY),
            };

            return (string) $encoded;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
