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
        Schema::create('gestures', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "hello", "thank_you", "yes"
            $table->string('label'); // Display label for the gesture
            $table->text('description')->nullable(); // Description of how to perform the gesture
            $table->boolean('is_active')->default(true); // Whether this gesture is active for detection
            $table->boolean('supports_dual_hand')->default(false); // Whether this gesture supports dual hands
            $table->json('metadata')->nullable(); // Additional metadata for the gesture
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gestures');
    }
};
