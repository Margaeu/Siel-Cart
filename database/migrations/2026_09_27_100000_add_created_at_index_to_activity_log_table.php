<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The audit trail is read newest-first and counted by day, and until now it had
 * no index supporting either. spatie/laravel-activitylog's own migration indexes
 * `log_name` and the two morph pairs only, so on MySQL 8.4/InnoDB both of these
 * were full table scans:
 *
 *   ActivityLogResource's table  ORDER BY created_at DESC  (defaultSort)
 *   RecentActivity               ORDER BY created_at DESC, id DESC LIMIT 10
 *   ActivityLogStats             WHERE created_at BETWEEN <midnight> AND <midnight>
 *
 * EXPLAIN on the live table confirmed it: type=ALL, possible_keys=NULL, and
 * "Using filesort" for the ordering query. That is invisible at a couple of
 * hundred rows and is the query behind the page every super admin lands on at
 * login, so it only gets worse.
 *
 * (created_at, id) rather than created_at alone. On InnoDB a secondary index
 * entry already carries the clustered primary key, so created_at alone would
 * very likely serve `ORDER BY created_at DESC, id DESC` too -- but only through
 * the optimizer's index-extensions behaviour, and no isolated MySQL instance was
 * available here to confirm the post-index plan. Naming `id` makes the tiebreaker
 * an explicitly declared key part, so the ordering is index-ordered by
 * definition. `created_at` stays leftmost, so the date-range scan above is served
 * by the same index.
 *
 * `event` is deliberately not in it: ActivityLogStats filters only by date and
 * aggregates over event with SUM(CASE ...), so an (event, created_at) index would
 * not serve that scan -- event is not a leftmost prefix of anything queried.
 *
 * Connection and table name come from config the same way the create migration
 * takes them, so this follows the log wherever activitylog.php points it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('activitylog.database_connection'))
            ->table(config('activitylog.table_name'), function (Blueprint $table) {
                $table->index(['created_at', 'id']);
            });
    }

    public function down(): void
    {
        Schema::connection(config('activitylog.database_connection'))
            ->table(config('activitylog.table_name'), function (Blueprint $table) {
                $table->dropIndex(['created_at', 'id']);
            });
    }
};
