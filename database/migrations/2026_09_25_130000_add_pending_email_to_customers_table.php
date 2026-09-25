<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Holds the new address between "Change" and clicking the
            // confirmation link mailed to it. The live `email` column (and
            // email_verified_at) is never touched until that link is
            // followed, so a customer stays reachable at their old address
            // if they never confirm.
            $table->string('pending_email')->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('pending_email');
        });
    }
};
