<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Categories no longer have descriptions. The create-categories migration
     * was edited to stop creating the column, but databases that ran the old
     * version still have it. Guarded by hasColumn() so a fresh database, which
     * never had the column, passes through untouched.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('categories', 'description')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }

    /**
     * Restores the column's shape only; the dropped text is not recoverable.
     */
    public function down(): void
    {
        if (Schema::hasColumn('categories', 'description')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->text('description')->nullable()->after('slug');
        });
    }
};
