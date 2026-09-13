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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            // The customer filing the report.
            $table->foreignId('reporter_customer_id')->constrained('customers')->cascadeOnDelete();
            // The customer being reported (the review's author).
            $table->foreignId('reported_customer_id')->constrained('customers')->cascadeOnDelete();
            // The review this report is about. Kept even if the review is later
            // removed, so admins still have context on what was reported.
            $table->foreignId('review_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason');
            $table->text('details')->nullable();
            $table->string('status')->default('pending'); // pending, reviewed, dismissed
            $table->timestamps();

            // A customer can only report a given review once.
            $table->unique(['reporter_customer_id', 'review_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};