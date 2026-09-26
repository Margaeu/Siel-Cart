<?php

namespace App\Filament\AvatarProviders;

use App\Models\Theme;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SielAvatarProvider implements AvatarProvider
{
    // CLSU green, the panel's primary-600 before theming existed. Used when no
    // theme is active, or the active one's colour can't be read as a hex.
    private const FALLBACK_COLOR = [85, 127, 19];

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
        $color = $this->themeColor();

        if ($avatar = $this->renderAcuminAvatar($initials, $color)) {
            return $avatar;
        }

        $initials = htmlspecialchars($initials, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $hex = vsprintf('#%02X%02X%02X', $color);

        // White disc, initials in the theme's primary colour. The ring is not
        // decoration: the avatar sits on the coloured topbar *and* on white
        // surfaces (the account widget, the user menu panel), and without an
        // outline the disc would disappear into the white ones, leaving the
        // initials floating.
        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" role="img">
            <circle cx="20" cy="20" r="19.25" fill="#FFFFFF" stroke="{$hex}" stroke-width="1.5"/>
            <text x="20" y="21" fill="{$hex}" font-family="'Acumin Pro', sans-serif" font-size="14" font-weight="700" text-anchor="middle" dominant-baseline="middle">{$initials}</text>
        </svg>
        SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * The active theme's primary colour as [r, g, b], so the avatar follows
     * Admin -> Design -> Color Themes like the rest of the panel instead of
     * staying CLSU green. The verbatim hex rather than Filament's generated
     * primary-600, for the reason given on $primaryColorRaw in
     * AdminPanelProvider: that shade does not reproduce the theme's colour.
     * The lookup is wrapped for the same reason as there, since it can run
     * before the themes table exists.
     *
     * @return array{int, int, int}
     */
    private function themeColor(): array
    {
        try {
            $hex = Theme::active()->value('primary_color');
        } catch (\Throwable) {
            return self::FALLBACK_COLOR;
        }

        $hex = ltrim(trim((string) $hex), '#');

        if (preg_match('/^[0-9a-f]{3}$/i', $hex)) {
            $hex = preg_replace('/(.)/', '$1$1', $hex);
        }

        if (! preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            return self::FALLBACK_COLOR;
        }

        return array_map('hexdec', str_split($hex, 2));
    }

    /**
     * @param  array{int, int, int}  $color
     */
    private function renderAcuminAvatar(string $initials, array $color): ?string
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

        // Mirrors the SVG fallback above: white disc, ring and initials in the
        // theme colour. The ring is drawn as a coloured disc with a smaller white
        // one on top of it rather than with imageellipse(), whose stroke comes
        // out ragged.
        $accent = imagecolorallocate($image, ...$color);
        $white = imagecolorallocate($image, 255, 255, 255);
        $foreground = $accent;
        $ring = 4;
        imagefilledellipse($image, $size / 2, $size / 2, $size, $size, $accent);
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
