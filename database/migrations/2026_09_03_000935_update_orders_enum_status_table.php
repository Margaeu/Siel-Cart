<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'processing', 'ready_for_pickup', 'completed', 'cancelled', 'return_requested', 'return_completed') DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'processing', 'ready_for_pickup', 'completed', 'cancelled', 'return_requested') DEFAULT 'pending'");
    }
};