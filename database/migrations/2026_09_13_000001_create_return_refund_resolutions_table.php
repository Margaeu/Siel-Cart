<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The final outcome of a return, recorded against the order line it was
     * about. UBAP settles returns outside the shop; this only records what was
     * decided, so nothing here ever edits the line itself.
     */
    public function up(): void
    {
        Schema::create('return_refund_resolutions', function (Blueprint $table) {
            $table->id();

            // Goes with the line, which goes with its order. That only happens
            // when an order is force-deleted, which purges the rest of its
            // history too -- an ordinary delete is soft and keeps everything.
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();

            $table->string('type');   // refund, exchange
            $table->string('reason'); // defective, damaged, seller_error

            // How many of the line's units this covers. A line of three mugs
            // can have one refunded and leave the other two alone.
            $table->unsignedInteger('quantity');

            // Refunds only. An exchange settles in goods, not money.
            $table->decimal('refund_amount', 10, 2)->nullable();

            // Exchanges only. Null on delete, never cascade, for the same
            // reason as order_items: the catalogue can lose the replacement
            // later, and the names copied below keep the record readable.
            $table->foreignId('replacement_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('replacement_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('replacement_product_name')->nullable();
            $table->string('replacement_variant_name')->nullable();

            // Seller-error exchanges only: the item UBAP released in error and
            // the condition it came back in. Any other exchange leaves these
            // null. The names preserve the history if the catalogue entries
            // are deleted later.
            $table->foreignId('incorrect_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('incorrect_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('incorrect_product_name')->nullable();
            $table->string('incorrect_variant_name')->nullable();
            $table->string('incorrect_item_condition')->nullable(); // sellable, damaged, defective

            $table->text('notes')->nullable();

            // The admin who recorded it. Nullable so removing an admin account
            // keeps the resolution record.
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();

            // When UBAP carried the decision out, which need not be when it
            // was entered here -- created_at keeps that. A datetime rather than
            // a timestamp, so no server setting can give it an automatic
            // default or ON UPDATE behaviour.
            $table->dateTime('processed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_refund_resolutions');
    }
};
