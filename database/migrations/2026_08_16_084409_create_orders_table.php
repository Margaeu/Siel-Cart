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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // Order amounts (pickup only)
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);

            // Pickup details
            $table->string('pickup_contact_name'); // who picked up the order
            $table->string('pickup_contact_phone'); // number who picked up the order
            $table->date('pickup_date')->nullable();
            $table->string('pickup_location')->nullable();
            $table->string('claim_number')->unique()->nullable();

            // Payment & Status
            $table->enum('payment_method', ['cash_on_pickup'])->default('cash_on_pickup');
            $table->string('payment_status')->default('pending'); // pending, paid, failed, refunded
            $table->timestamp('paid_at')->nullable();
            $table->enum('status', ['pending', 'processing', 'ready_for_pickup', 'completed', 'cancelled'])->default('pending');
            $table->text('customer_notes')->nullable();
            $table->text('admin_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
