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
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('primary_color')->default('#1E6031');
            $table->string('secondary_color')->default('#E0A70D');

            // One of the pre-approved Bunny Fonts presets (see theme-styles.blade.php).
            // Null when a custom uploaded font is used instead.
            $table->string('font_family')->nullable();

            // Custom font uploaded to R2. When set, takes precedence over font_family.
            $table->string('custom_font_name')->nullable();
            $table->string('custom_font_path')->nullable();

            // Exactly one theme is active at a time; enforced in the Theme model.
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};
