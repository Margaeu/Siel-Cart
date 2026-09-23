<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The original (product_id, customer_id) unique index let a customer
     * review a product only once ever. A customer who orders the same
     * product again in a separate completed order should be able to leave a
     * new review for that order, so the constraint now also keys on
     * order_id.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            // The old unique index is the only index covering product_id, so
            // its foreign key needs a stand-in before that index can drop.
            $table->index('product_id', 'reviews_product_id_index');
            $table->dropUnique(['product_id', 'customer_id']);
            $table->unique(['product_id', 'customer_id', 'order_id']);
            // The new unique index's leftmost column already covers product_id.
            $table->dropIndex('reviews_product_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->index('product_id', 'reviews_product_id_index');
            $table->dropUnique(['product_id', 'customer_id', 'order_id']);
            $table->unique(['product_id', 'customer_id']);
            $table->dropIndex('reviews_product_id_index');
        });
    }
};
