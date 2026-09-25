<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The first pickup schedule UBAP gave an order is kept once the order is
     * moved to another day, so both the admin and the customer can see what
     * it was changed from. Every intermediate move is in the status history.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->date('original_pickup_date')->nullable()->after('pickup_slot');
            $table->string('original_pickup_slot')->nullable()->after('original_pickup_date');
            $table->timestamp('rescheduled_at')->nullable()->after('original_pickup_slot');
            $table->unsignedSmallInteger('reschedule_count')->default(0)->after('rescheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'original_pickup_date',
                'original_pickup_slot',
                'rescheduled_at',
                'reschedule_count',
            ]);
        });
    }
};
