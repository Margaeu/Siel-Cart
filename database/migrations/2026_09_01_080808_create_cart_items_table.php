<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create the cart_items table.
        // This stores the products and quantities inside each customer's cart.
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();

            // Identify which cart this item belongs to.
            // If the cart is deleted, its items are automatically deleted too.
            $table->foreignId('cart_id')
                ->constrained('carts')
                ->cascadeOnDelete();

            // Identify the product added to the cart.
            // If the product is deleted, the related cart item is also deleted.
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            // Store the selected product variant, if the product has one.
            // This is optional because some products may not have variants.
            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->nullOnDelete();

            // Store how many units of the product the customer wants.
            $table->unsignedInteger('quantity');

            // Store when the cart item was created and last updated.
            $table->timestamps();

            // Add an index to make cart/product lookups faster.
            $table->index(['cart_id', 'product_id']);
        });
    }

    public function down(): void
    {
        // Remove the cart_items table when this migration is rolled back.
        Schema::dropIfExists('cart_items');
    }
};