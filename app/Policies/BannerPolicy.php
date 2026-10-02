<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Banner;
use Illuminate\Auth\Access\HandlesAuthorization;

class BannerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Banner');
    }

    public function view(AuthUser $authUser, Banner $banner): bool
    {
        return $authUser->can('View:Banner');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Banner');
    }

    public function update(AuthUser $authUser, Banner $banner): bool
    {
        return $authUser->can('Update:Banner');
    }

    public function delete(AuthUser $authUser, Banner $banner): bool
    {
        return $authUser->can('Delete:Banner');
    }

}