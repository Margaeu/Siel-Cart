<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Theme;
use Illuminate\Auth\Access\HandlesAuthorization;

class ThemePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Theme');
    }

    public function view(AuthUser $authUser, Theme $theme): bool
    {
        return $authUser->can('View:Theme');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Theme');
    }

    public function update(AuthUser $authUser, Theme $theme): bool
    {
        return $authUser->can('Update:Theme');
    }

    public function delete(AuthUser $authUser, Theme $theme): bool
    {
        return $authUser->can('Delete:Theme');
    }

}