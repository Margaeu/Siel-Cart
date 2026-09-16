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
            // Add the customer's date of birth to the existing customers table.
            // Nullable allows existing customer accounts to remain valid after this migration.
            $table->date('date_of_birth')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Remove the date of birth column if this migration is rolled back.
            $table->dropColumn('date_of_birth');
        });
    }
};