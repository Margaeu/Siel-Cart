<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Traits\LogsActivity;

/**
 * LogsActivity, restricted to changes made by a signed-in admin.
 *
 * The activity log is an admin audit trail, but a model event fires for
 * anyone who causes it: a customer checking out decrements stock and creates
 * an order, and cancelling one restocks it. Those arrived as "System" entries
 * that no admin did and no admin could act on, burying the real ones.
 *
 * The check is the `web` guard (the admin realm, also what Spatie resolves the
 * causer from), so it needs no list of customer code paths to keep up to date.
 * Console work (seeders, tinker, scheduled jobs) has no admin either and is
 * skipped for the same reason. Admin sign-in events are unaffected: the auth
 * listeners write through activity() directly, not through model events.
 */
trait LogsAdminActivity
{
    use LogsActivity {
        shouldLogEvent as protected shouldLogEventForAnyone;
    }

    protected function shouldLogEvent(string $eventName): bool
    {
        return auth(config('activitylog.default_auth_driver'))->check()
            && $this->shouldLogEventForAnyone($eventName);
    }
}
