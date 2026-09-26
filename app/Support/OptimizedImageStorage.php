<?php

namespace App\Support;

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
 * SVGs and GIFs are stored as-is: GD flattens an animated GIF to its first
 * frame and can't touch vector paths. Any decode failure also falls back to
 * the original bytes, so a corrupt or unusual file never blocks the upload --
 * this is a size optimization, not a validation gate.
 */
class OptimizedImageStorage
{
    private const SKIP_EXTENSIONS = ['svg', 'gif'];

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

    private static function reencode(string $contents, string $extension, int $maxWidth): ?string
    {
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
