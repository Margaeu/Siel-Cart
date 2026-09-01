<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create the carts table.
        // Each customer will have one permanent cart stored in the database.
        Schema::create('carts', function (Blueprint $table) {
            $table->id();

            // Connect the cart to the customer who owns it.
            // If the customer is deleted, their cart will also be deleted.
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            // Store when the cart was created and last updated.
            $table->timestamps();

            // Make sure each customer can only have one cart.
            $table->unique('customer_id');
        });
    }

    public function down(): void
    {
        // Remove the carts table when this migration is rolled back.
        Schema::dropIfExists('carts');
    }
};