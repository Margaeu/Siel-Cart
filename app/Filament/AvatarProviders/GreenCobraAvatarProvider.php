<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GreenCobraAvatarProvider implements AvatarProvider
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

        $initials = htmlspecialchars($initials ?: 'A', ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" role="img">
            <rect width="40" height="40" rx="20" fill="#557F13"/>
            <text x="20" y="21" fill="#FFFFFF" font-family="Arial, sans-serif" font-size="14" font-weight="700" text-anchor="middle" dominant-baseline="middle">{$initials}</text>
        </svg>
        SVG;

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
