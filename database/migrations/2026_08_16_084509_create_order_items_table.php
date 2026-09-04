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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Null on delete, never cascade. Products can be force-deleted
            // from the admin panel, and cascading would take the line items
            // with them -- past orders would lose rows while keeping their
            // total, so the lines would stop adding up to what was charged.
            // The link is only used to reach the live product; everything the
            // order needs to display itself is snapshotted in the columns
            // below, so a line that outlives its product still reads fine.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            // Nullable because this snapshots products.sku, which is itself
            // nullable -- a product sold by variant has no SKU of its own, so
            // the admin form hides the field and nothing is submitted for it.
            // A variant line copies the variant's SKU, which is always set.
            $table->string('product_sku')->nullable();
            $table->string('variant_name')->nullable();
            $table->string('product_image')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('quantity');
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
