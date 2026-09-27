<?php

namespace Tests\Feature;

use App\Rules\ImageWithinPixelBudget;
use App\Support\OptimizedImageStorage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * GD decodes to an uncompressed bitmap, so what an upload costs in memory
 * tracks its pixel count, not its file size -- an 8000x6000 photo is around
 * 1.2 MB on disk and wants ~202 MB of bitmap. Going over memory_limit is a
 * fatal error rather than an exception, so it cannot be caught: the PHP-FPM
 * worker dies mid-request and the admin sees a dead page. These tests pin
 * both halves of the guard against that.
 *
 * The fixtures are PNG headers with no pixel data. getimagesize() only reads
 * the header, which is exactly why the check is safe on a file that would be
 * fatal to decode -- and it lets these tests describe an 80 megapixel image
 * in 33 bytes.
 */
class ImagePixelBudgetTest extends TestCase
{
    private function pngHeader(int $width, int $height): string
    {
        $ihdr = 'IHDR'.pack('N', $width).pack('N', $height).chr(8).chr(2).chr(0).chr(0).chr(0);

        return "\x89PNG\r\n\x1a\n".pack('N', 13).$ihdr.pack('N', crc32($ihdr));
    }

    private function upload(string $contents, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pxb');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function failureFor(UploadedFile $file): ?string
    {
        $message = null;

        (new ImageWithinPixelBudget)->validate('image', $file, function (string $m) use (&$message) {
            $message = $m;
        });

        return $message;
    }

    public function test_pixel_count_is_read_from_the_header_without_decoding(): void
    {
        $this->assertSame(80_000_000, OptimizedImageStorage::pixelCountOf($this->pngHeader(10000, 8000)));
        $this->assertSame(12_000_000, OptimizedImageStorage::pixelCountOf($this->pngHeader(4000, 3000)));
    }

    public function test_unreadable_dimensions_are_not_treated_as_oversized(): void
    {
        // Video, SVG and anything malformed land here. Guessing would either
        // block legitimate uploads or, worse, wave through a real one.
        $this->assertNull(OptimizedImageStorage::pixelCountOf('not an image at all'));
        $this->assertFalse(OptimizedImageStorage::exceedsPixelBudget('not an image at all'));
    }

    public function test_the_budget_boundary_is_inclusive(): void
    {
        $atLimit = OptimizedImageStorage::MAX_PIXELS;

        $this->assertFalse(OptimizedImageStorage::exceedsPixelBudget($this->pngHeader(4000, $atLimit / 4000)));
        $this->assertTrue(OptimizedImageStorage::exceedsPixelBudget($this->pngHeader(4000, ($atLimit / 4000) + 1)));
    }

    public function test_the_rule_rejects_an_image_over_the_budget_and_says_what_to_do(): void
    {
        $message = $this->failureFor($this->upload($this->pngHeader(10000, 8000), 'huge.png'));

        $this->assertNotNull($message, 'An 80 megapixel image must not reach GD.');
        $this->assertStringContainsString('80 megapixels', $message);
        $this->assertStringContainsString('10000×8000', $message);
        $this->assertStringContainsString('resize', $message);
    }

    public function test_the_rule_accepts_an_ordinary_photo(): void
    {
        // 12 MP is a normal phone camera image and must keep working.
        $this->assertNull($this->failureFor($this->upload($this->pngHeader(4000, 3000), 'photo.png')));
    }

    public function test_the_rule_ignores_files_whose_dimensions_cannot_be_read(): void
    {
        // A banner clip is a legitimate upload on the same field; only a
        // known-oversized image may fail this rule.
        $this->assertNull($this->failureFor($this->upload('\x00\x00\x00 ftypmp42 not really a video', 'clip.mp4')));
        $this->assertNull($this->failureFor($this->upload('<svg xmlns="http://www.w3.org/2000/svg"/>', 'logo.svg')));
    }

    public function test_non_uploads_are_left_to_other_rules(): void
    {
        $message = null;
        (new ImageWithinPixelBudget)->validate('image', 'a string, not a file', function ($m) use (&$message) {
            $message = $m;
        });
        $this->assertNull($message);
    }
}
