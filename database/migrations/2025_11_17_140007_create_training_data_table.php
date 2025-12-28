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
        Schema::create('training_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gesture_id')->constrained()->onDelete('cascade');
            $table->json('landmark_data'); // Raw landmark data from MediaPipe
            $table->json('normalized_data')->nullable(); // Normalized landmark data
            $table->float('confidence_score')->nullable(); // Confidence score from detection
            $table->integer('hand_count')->default(1); // Number of hands detected (1 or 2)
            $table->json('hand_data')->nullable(); // Complete hand data for all detected hands
            $table->string('image_path')->nullable(); // Path to captured image (optional)
            $table->boolean('is_validated')->default(true); // Whether this data is validated for training
            $table->text('notes')->nullable(); // Additional notes about training data
            $table->json('metadata')->nullable(); // Additional metadata (hand type, capture conditions, etc.)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_data');
    }
};
