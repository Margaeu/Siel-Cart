<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * products.category_id was cascadeOnDelete(), so deleting a category
     * hard-deleted every product in it -- bypassing Product's soft deletes and
     * taking soft-deleted rows with it. RESTRICT makes the database refuse the
     * delete while any product row (active, inactive, or trashed) still points
     * at the category. The admin actions check first for a friendly message;
     * this constraint is what actually guarantees it.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->cascadeOnDelete();
        });
    }
};
