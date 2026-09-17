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

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" role="img">
            <rect width="40" height="40" rx="20" fill="#557F13"/>
            <text x="20" y="21" fill="#FFFFFF" font-family="'Acumin Pro', sans-serif" font-size="14" font-weight="700" text-anchor="middle" dominant-baseline="middle">{$initials}</text>
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

        $background = imagecolorallocate($image, 85, 127, 19);
        $foreground = imagecolorallocate($image, 255, 255, 255);
        imagefilledellipse($image, $size / 2, $size / 2, $size, $size, $background);

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
