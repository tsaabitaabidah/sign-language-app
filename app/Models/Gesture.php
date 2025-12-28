<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gesture extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'label',
        'description',
        'is_active',
        'supports_dual_hand',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'supports_dual_hand' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the training data for this gesture.
     */
    public function trainingData(): HasMany
    {
        return $this->hasMany(TrainingData::class);
    }

    /**
     * Get the validated training data for this gesture.
     */
    public function validatedTrainingData(): HasMany
    {
        return $this->trainingData()->where('is_validated', true);
    }

    /**
     * Get the training data count.
     */
    public function getTrainingDataCountAttribute(): int
    {
        return $this->trainingData()->count();
    }

    /**
     * Get the validated training data count.
     */
    public function getValidatedTrainingDataCountAttribute(): int
    {
        return $this->validatedTrainingData()->count();
    }

    /**
     * Get the average confidence score for training data.
     */
    public function getAverageConfidenceAttribute(): float
    {
        return $this->trainingData()->avg('confidence_score') ?? 0.0;
    }

    /**
     * Get the best confidence score for training data.
     */
    public function getBestConfidenceAttribute(): float
    {
        return $this->trainingData()->max('confidence_score') ?? 0.0;
    }

    /**
     * Scope a query to only include active gestures.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include gestures that support dual hands.
     */
    public function scopeSupportsDualHand($query)
    {
        return $query->where('supports_dual_hand', true);
    }

    /**
     * Scope a query to only include gestures that have sufficient training data.
     */
    public function scopeWithSufficientTrainingData($query, int $minimumSamples = 5)
    {
        return $query->whereHas('trainingData', function ($query) use ($minimumSamples) {
            $query->where('is_validated', true)
                  ->havingRaw('COUNT(*) >= ?', [$minimumSamples]);
        });
    }

    /**
     * Get gestures ready for detection (active with sufficient training data).
     */
    public static function getReadyForDetection(int $minimumSamples = 5)
    {
        return self::active()
                   ->with(['trainingData' => function ($query) {
                       $query->where('is_validated', true);
                   }])
                   ->whereHas('trainingData', function ($query) use ($minimumSamples) {
                       $query->where('is_validated', true)
                             ->havingRaw('COUNT(*) >= ?', [$minimumSamples]);
                   })
                   ->get();
    }

    /**
     * Check if this gesture is ready for detection.
     */
    public function isReadyForDetection(int $minimumSamples = 5): bool
    {
        return $this->is_active && $this->validated_training_data_count >= $minimumSamples;
    }

    /**
     * Get training data quality metrics.
     */
    public function getTrainingQualityAttribute(): array
    {
        $totalSamples = $this->training_data_count;
        $validatedSamples = $this->validated_training_data_count;
        $avgConfidence = $this->average_confidence;
        $bestConfidence = $this->best_confidence;

        return [
            'total_samples' => $totalSamples,
            'validated_samples' => $validatedSamples,
            'validation_rate' => $totalSamples > 0 ? ($validatedSamples / $totalSamples) * 100 : 0,
            'average_confidence' => $avgConfidence,
            'best_confidence' => $bestConfidence,
            'quality_score' => $this->calculateQualityScore(),
            'is_ready' => $this->isReadyForDetection(),
        ];
    }

    /**
     * Calculate overall quality score for this gesture's training data.
     */
    private function calculateQualityScore(): float
    {
        $totalSamples = $this->training_data_count;
        $validatedSamples = $this->validated_training_data_count;
        $avgConfidence = $this->average_confidence;

        if ($totalSamples === 0) return 0;

        // Factors: validation rate (40%), sample count (30%), average confidence (30%)
        $validationRate = ($validatedSamples / $totalSamples) * 100;
        $sampleScore = min(100, ($validatedSamples / 10) * 100); // Cap at 10 samples
        $confidenceScore = $avgConfidence * 100;

        return ($validationRate * 0.4) + ($sampleScore * 0.3) + ($confidenceScore * 0.3);
    }

    /**
     * Get the most recent training data.
     */
    public function getRecentTrainingData(int $limit = 10)
    {
        return $this->trainingData()
                   ->latest()
                   ->limit($limit)
                   ->get();
    }

    /**
     * Get training data by hand count.
     */
    public function getTrainingDataByHandCount(int $handCount)
    {
        return $this->trainingData()
                   ->where('hand_count', $handCount)
                   ->get();
    }

    /**
     * Get dual-hand training data.
     */
    public function getDualHandTrainingData()
    {
        return $this->getTrainingDataByHandCount(2);
    }

    /**
     * Get single-hand training data.
     */
    public function getSingleHandTrainingData()
    {
        return $this->getTrainingDataByHandCount(1);
    }
}
