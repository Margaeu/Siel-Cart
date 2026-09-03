<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'pickup_contact_name')) {
                $table->string('pickup_contact_name')->nullable()->after('status');
            }
            if (!Schema::hasColumn('orders', 'pickup_contact_phone')) {
                $table->string('pickup_contact_phone')->nullable()->after('pickup_contact_name');
            }
            if (!Schema::hasColumn('orders', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('paid_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['pickup_contact_name', 'pickup_contact_phone', 'completed_at']);
        });
    }
};