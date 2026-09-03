<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('cancellation_reason')->nullable()->after('admin_notes');
            $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
            $table->timestamp('completed_at')->nullable()->after('cancelled_at');
            $table->timestamp('return_deadline')->nullable()->after('completed_at');
            $table->string('pickup_slot')->nullable()->after('pickup_date');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('product_image')->nullable()->after('variant_name');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['cancellation_reason', 'cancelled_at', 'completed_at', 'return_deadline', 'pickup_slot']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('product_image');
        });
    }
};