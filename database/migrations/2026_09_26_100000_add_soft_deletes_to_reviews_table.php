<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A customer gets one review per completed order for a product. Hard
     * deleting a review (e.g. after a report) freed that order up again, so
     * the author could simply post a new one. Removed reviews are now kept,
     * soft-deleted, so the purchase stays "used" -- see Review.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
