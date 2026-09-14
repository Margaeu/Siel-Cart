<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OrderItemResolution;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Who may see and record refunds and exchanges under Returns & Refunds.
 *
 * There are no permissions of its own. Seeing a resolution follows seeing
 * orders, since the order page has always shown them. Recording one follows
 * RecordResolution:Order, the same ability OrderItemResolutionService checks
 * against the order itself.
 *
 * A recorded resolution is history (see OrderItemResolution::booted()), so
 * every ability that would change, remove or copy one is refused here. Each
 * needs its own method: Filament allows an ability the policy does not define.
 */
class OrderItemResolutionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Order');
    }

    public function view(AuthUser $authUser, OrderItemResolution $orderItemResolution): bool
    {
        return $authUser->can('View:Order');
    }

    /** The form lists orders to choose from, so recording needs seeing them too. */
    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('RecordResolution:Order') && $authUser->can('ViewAny:Order');
    }

    public function update(AuthUser $authUser, OrderItemResolution $orderItemResolution): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, OrderItemResolution $orderItemResolution): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restore(AuthUser $authUser, OrderItemResolution $orderItemResolution): bool
    {
        return false;
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, OrderItemResolution $orderItemResolution): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function replicate(AuthUser $authUser, OrderItemResolution $orderItemResolution): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
