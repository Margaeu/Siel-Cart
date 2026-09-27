<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverts 2026_09_25_120000_add_pickup_reschedule_columns_to_orders_table,
     * which was deleted along with the rest of the reschedule feature. Guarded
     * with hasColumn() so this is safe both on a database that ran the
     * original migration and on a fresh one that never had these columns.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = array_filter(
                ['original_pickup_date', 'original_pickup_slot', 'rescheduled_at', 'reschedule_count'],
                fn (string $column): bool => Schema::hasColumn('orders', $column),
            );

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'original_pickup_date')) {
                $table->date('original_pickup_date')->nullable()->after('pickup_slot');
            }

            if (! Schema::hasColumn('orders', 'original_pickup_slot')) {
                $table->string('original_pickup_slot')->nullable()->after('original_pickup_date');
            }

            if (! Schema::hasColumn('orders', 'rescheduled_at')) {
                $table->timestamp('rescheduled_at')->nullable()->after('original_pickup_slot');
            }

            if (! Schema::hasColumn('orders', 'reschedule_count')) {
                $table->unsignedSmallInteger('reschedule_count')->default(0)->after('rescheduled_at');
            }
        });
    }
};
