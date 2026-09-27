<?php

namespace App\Filament\Pages\Dashboard\Widgets\Concerns;

use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Facades\Filament;

trait HasDesignWidgetAccess
{
    use HasWidgetShield {
        canView as private canViewWithShield;
    }

    public static function canView(): bool
    {
        $user = Filament::auth()?->user();

        if (! $user instanceof User) {
            return false;
        }

        // StratCom keeps its design dashboard. Super admins select its
        // individual widgets using the same Shield toggles as shop widgets.
        return $user->isDesignOnlyAdmin()
            || ($user->hasRole('super_admin') && static::canViewWithShield());
    }
}
