<?php

return [

    /*
     * If set to false, no activities will be saved to the database.
     */
    'enabled' => env('ACTIVITY_LOGGER_ENABLED', true),

    /*
     * When the clean-command is executed, all recording activities older than
     * the number of days specified here will be deleted.
     */
    'delete_records_older_than_days' => 365,

    /*
     * If no log name is passed to the activity() helper
     * we use this default log name.
     */
    'default_log_name' => 'default',

    /*
     * Pinned to the admin guard on purpose. This is an admin-only audit
     * trail (see CLAUDE.md), but Laravel's own Authenticate middleware
     * calls Auth::shouldUse('customer') for the duration of any request
     * that passes an `auth:customer` check (checkout, my-account, order
     * cancellation). Left null, the resolver falls back to "whatever the
     * current default guard is" and would attribute model changes made
     * during those requests — e.g. the stock restore on a customer's own
     * order cancellation — to the customer instead of leaving them
     * causer-less. Pinning to 'web' keeps causer resolution untouched by
     * that guard-switching side effect.
     */
    'default_auth_driver' => 'web',

    /*
     * If set to true, the subject returns soft deleted models.
     */
    'subject_returns_soft_deleted_models' => false,

    /*
     * This model will be used to log activity.
     * It should implement the Spatie\Activitylog\Contracts\Activity interface
     * and extend Illuminate\Database\Eloquent\Model.
     */
    'activity_model' => \Spatie\Activitylog\Models\Activity::class,

    /*
     * This is the name of the table that will be created by the migration and
     * used by the Activity model shipped with this package.
     */
    'table_name' => env('ACTIVITY_LOGGER_TABLE_NAME', 'activity_log'),

    /*
     * This is the database connection that will be used by the migration and
     * the Activity model shipped with this package. In case it's not set
     * Laravel's database.default will be used instead.
     */
    'database_connection' => env('ACTIVITY_LOGGER_DB_CONNECTION'),
];
