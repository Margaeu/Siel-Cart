<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Review;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReviewPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Review');
    }

    public function view(AuthUser $authUser, Review $review): bool
    {
        return $authUser->can('View:Review');
    }

    public function delete(AuthUser $authUser, Review $review): bool
    {
        return $authUser->can('Delete:Review');
    }

}