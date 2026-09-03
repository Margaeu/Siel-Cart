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
            $table->date('pickup_date')->nullable();
            $table->string('pickup_slot')->nullable();
            $table->string('pickup_location')->default('UBAP Office');
            $table->string('claim_number')->unique()->nullable(); // can only be generated when it is ready for pickup

            // Payment & Status
            $table->enum('payment_method', ['cash_on_pickup'])->default('cash_on_pickup');
            $table->string('payment_status')->default('pending'); // pending, paid, failed, refunded
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('return_deadline')->nullable();
            $table->enum('status', ['pending', 'processing', 'ready_for_pickup', 'completed', 'cancelled', 'return_requested', 'return_completed'])->default('pending');
            $table->string('claimant_name')->nullable(); // who received the order
            $table->string('claimant_phone')->nullable(); // number of the claimant
            $table->text('admin_notes')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();

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