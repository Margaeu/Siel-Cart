<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SielAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $initials = Str::of(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => Str::upper(Str::substr($word, 0, 1)))
            ->implode('');

        $initials = $initials ?: 'A';

        if ($avatar = $this->renderAcuminAvatar($initials)) {
            return $avatar;
        }

        $initials = htmlspecialchars($initials, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        // White disc, CLSU green initials. The green ring is not decoration: the
        // avatar sits on the green topbar *and* on white surfaces (the account
        // widget, the user menu panel), and without an outline the disc would
        // disappear into the white ones, leaving the initials floating.
        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" role="img">
            <circle cx="20" cy="20" r="19.25" fill="#FFFFFF" stroke="#557F13" stroke-width="1.5"/>
            <text x="20" y="21" fill="#557F13" font-family="'Acumin Pro', sans-serif" font-size="14" font-weight="700" text-anchor="middle" dominant-baseline="middle">{$initials}</text>
        </svg>
        SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function renderAcuminAvatar(string $initials): ?string
    {
        if (! function_exists('imagettftext')) {
            return null;
        }

        $font = public_path('fonts/filament/filament/acumin-pro/Acumin-BdPro.otf');

        if (! is_file($font)) {
            return null;
        }

        $size = 120;
        $image = imagecreatetruecolor($size, $size);

        if ($image === false) {
            return null;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
        imagealphablending($image, true);

        // Mirrors the SVG fallback below: white disc, CLSU green ring and initials.
        // The ring is drawn as a green disc with a smaller white one on top of it
        // rather than with imageellipse(), whose stroke comes out ragged.
        $green = imagecolorallocate($image, 85, 127, 19);
        $white = imagecolorallocate($image, 255, 255, 255);
        $foreground = $green;
        $ring = 4;
        imagefilledellipse($image, $size / 2, $size / 2, $size, $size, $green);
        imagefilledellipse($image, $size / 2, $size / 2, $size - ($ring * 2), $size - ($ring * 2), $white);

        $fontSize = 42;
        $bounds = imagettfbbox($fontSize, 0, $font, $initials);

        if ($bounds === false) {
            imagedestroy($image);

            return null;
        }

        $left = min($bounds[0], $bounds[2], $bounds[4], $bounds[6]);
        $right = max($bounds[0], $bounds[2], $bounds[4], $bounds[6]);
        $top = min($bounds[1], $bounds[3], $bounds[5], $bounds[7]);
        $bottom = max($bounds[1], $bounds[3], $bounds[5], $bounds[7]);
        $x = (int) round(($size - ($right - $left)) / 2 - $left);
        $y = (int) round(($size - ($bottom - $top)) / 2 - $top);

        if (imagettftext($image, $fontSize, 0, $x, $y, $foreground, $font, $initials) === false) {
            imagedestroy($image);

            return null;
        }

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return is_string($png)
            ? 'data:image/png;base64,'.base64_encode($png)
            : null;
    }
}
