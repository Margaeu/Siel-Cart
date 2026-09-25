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
        Schema::table('cart_items', function (Blueprint $table) {
            // Snapshot of the image shown for this item when it was added or
            // last updated, mirroring order_items.product_image. The cart
            // still prefers the live product/variant image first -- this only
            // covers the item once that live image, variant, or product is
            // removed from the catalogue, so the row does not fall back to a
            // broken <img> tag while it is still sitting in the cart.
            $table->string('product_image')->nullable()->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn('product_image');
        });
    }
};
