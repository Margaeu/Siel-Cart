<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:User');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:User');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:User');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:User');
    }

    public function delete(AuthUser $authUser, ?User $user = null): Response
    {
        return static::guardPanelAccessLoss($authUser, $user, 'Delete:User', 'delete');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:User');
    }

    public function forceDelete(AuthUser $authUser, ?User $user = null): Response
    {
        return static::guardPanelAccessLoss($authUser, $user, 'ForceDelete:User', 'delete');
    }

    /**
     * Deleting an admin strips their panel access just as deactivating them
     * does, so it answers to the same guard: no self-lockout, and never the
     * last active super admin. The denial carries a message so that bulk
     * deletes can tell the operator which records were skipped and why.
     */
    private static function guardPanelAccessLoss(AuthUser $authUser, ?User $user, string $permission, string $action): Response
    {
        if (! $authUser->can($permission)) {
            return Response::deny();
        }

        if (! $user instanceof User) {
            return Response::allow();
        }

        $actor = $authUser instanceof User ? $authUser : null;

        return $user->canLosePanelAccessBy($actor)
            ? Response::allow()
            : Response::deny($user->panelAccessLossBlockedReason($actor, $action));
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:User');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:User');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:User');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:User');
    }

}