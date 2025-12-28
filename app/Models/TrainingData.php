<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingData extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'gesture_id',
        'landmark_data',
        'normalized_data',
        'confidence_score',
        'hand_count',
        'hand_data',
        'image_path',
        'notes',
        'is_validated',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'landmark_data' => 'array',
        'normalized_data' => 'array',
        'confidence_score' => 'decimal:4',
        'hand_count' => 'integer',
        'hand_data' => 'array',
        'is_validated' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the gesture that owns the training data.
     */
    public function gesture(): BelongsTo
    {
        return $this->belongsTo(Gesture::class);
    }

    /**
     * Scope a query to only include validated training data.
     */
    public function scopeValidated($query)
    {
        return $query->where('is_validated', true);
    }

    /**
     * Scope a query to only include unvalidated training data.
     */
    public function scopeUnvalidated($query)
    {
        return $query->where('is_validated', false);
    }

    /**
     * Scope a query to filter by hand count.
     */
    public function scopeByHandCount($query, int $handCount)
    {
        return $query->where('hand_count', $handCount);
    }

    /**
     * Scope a query to only include single-hand data.
     */
    public function scopeSingleHand($query)
    {
        return $query->where('hand_count', 1);
    }

    /**
     * Scope a query to only include dual-hand data.
     */
    public function scopeDualHand($query)
    {
        return $query->where('hand_count', 2);
    }

    /**
     * Scope a query to filter by minimum confidence score.
     */
    public function scopeMinConfidence($query, float $minConfidence)
    {
        return $query->where('confidence_score', '>=', $minConfidence);
    }

    /**
     * Scope a query to filter by confidence score range.
     */
    public function scopeConfidenceRange($query, float $min, float $max)
    {
        return $query->whereBetween('confidence_score', [$min, $max]);
    }

    /**
     * Scope a query to get recent training data.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get the primary hand landmarks (for dual-hand gestures).
     */
    public function getPrimaryHandLandmarksAttribute(): array
    {
        if ($this->hand_count === 1) {
            return $this->landmark_data;
        }

        // For dual-hand, return the first hand as primary
        return $this->hand_data[0] ?? $this->landmark_data;
    }

    /**
     * Get the secondary hand landmarks (for dual-hand gestures).
     */
    public function getSecondaryHandLandmarksAttribute(): ?array
    {
        if ($this->hand_count === 1) {
            return null;
        }

        return $this->hand_data[1] ?? null;
    }

    /**
     * Check if this training data has high quality.
     */
    public function isHighQuality(): bool
    {
        return $this->confidence_score >= 0.8 && $this->is_validated;
    }

    /**
     * Check if this training data has medium quality.
     */
    public function isMediumQuality(): bool
    {
        return $this->confidence_score >= 0.6 && $this->confidence_score < 0.8;
    }

    /**
     * Check if this training data has low quality.
     */
    public function isLowQuality(): bool
    {
        return $this->confidence_score < 0.6;
    }

    /**
     * Get quality level as string.
     */
    public function getQualityLevelAttribute(): string
    {
        if ($this->isHighQuality()) return 'high';
        if ($this->isMediumQuality()) return 'medium';
        return 'low';
    }

    /**
     * Get quality color for UI display.
     */
    public function getQualityColorAttribute(): string
    {
        return match($this->quality_level) {
            'high' => 'green',
            'medium' => 'yellow',
            'low' => 'red',
            default => 'gray'
        };
    }

    /**
     * Get formatted confidence score as percentage.
     */
    public function getConfidencePercentageAttribute(): string
    {
        return number_format($this->confidence_score * 100, 1) . '%';
    }

    /**
     * Get device information from metadata.
     */
    public function getDeviceInfoAttribute(): ?array
    {
        return $this->metadata['device'] ?? null;
    }

    /**
     * Get lighting conditions from metadata.
     */
    public function getLightingConditionsAttribute(): ?string
    {
        return $this->metadata['lighting'] ?? null;
    }

    /**
     * Get capture environment information.
     */
    public function getCaptureEnvironmentAttribute(): array
    {
        return [
            'device' => $this->device_info,
            'lighting' => $this->lighting_conditions,
            'timestamp' => $this->created_at,
            'hand_count' => $this->hand_count,
            'confidence' => $this->confidence_percentage,
        ];
    }

    /**
     * Validate the landmark data structure.
     */
    public function validateLandmarkData(): bool
    {
        if (!is_array($this->landmark_data) || empty($this->landmark_data)) {
            return false;
        }

        // Check if we have the expected number of landmarks (21 per hand)
        $expectedLandmarks = 21 * $this->hand_count;
        
        if (count($this->landmark_data) !== $expectedLandmarks) {
            return false;
        }

        // Validate each landmark has x, y, z coordinates
        foreach ($this->landmark_data as $landmark) {
            if (!isset($landmark['x']) || !isset($landmark['y']) || !isset($landmark['z'])) {
                return false;
            }
            
            // Check if coordinates are within valid ranges
            if ($landmark['x'] < 0 || $landmark['x'] > 1 ||
                $landmark['y'] < 0 || $landmark['y'] > 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get landmark data for comparison (normalized).
     */
    public function getComparisonDataAttribute(): array
    {
        // Use pre-normalized data if available, otherwise normalize on the fly
        if ($this->normalized_data) {
            return $this->normalized_data;
        }

        return $this->normalizeLandmarks($this->landmark_data);
    }

    /**
     * Normalize landmark data for comparison.
     */
    private function normalizeLandmarks(array $landmarks): array
    {
        if (empty($landmarks)) {
            return [];
        }

        // Get wrist position (point 0) as reference
        $wrist = $landmarks[0];
        
        // Calculate bounding box to determine scale
        $minX = $maxX = $landmarks[0]['x'];
        $minY = $maxY = $landmarks[0]['y'];
        $minZ = $maxZ = $landmarks[0]['z'];
        
        foreach ($landmarks as $point) {
            $minX = min($minX, $point['x']);
            $maxX = max($maxX, $point['x']);
            $minY = min($minY, $point['y']);
            $maxY = max($maxY, $point['y']);
            $minZ = min($minZ, $point['z']);
            $maxZ = max($maxZ, $point['z']);
        }
        
        // Calculate scale (use max range across all dimensions)
        $rangeX = $maxX - $minX;
        $rangeY = $maxY - $minY;
        $rangeZ = $maxZ - $minZ;
        $scale = max($rangeX, $rangeY, $rangeZ);
        
        // Prevent division by zero
        if ($scale < 0.001) {
            $scale = 1.0;
        }
        
        // Normalize each point relative to wrist and scale
        $normalized = [];
        foreach ($landmarks as $point) {
            $normalized[] = [
                'x' => (($point['x'] ?? 0) - $wrist['x']) / $scale,
                'y' => (($point['y'] ?? 0) - $wrist['y']) / $scale,
                'z' => (($point['z'] ?? 0) - $wrist['z']) / $scale,
            ];
        }
        
        return $normalized;
    }

    /**
     * Create a duplicate of this training data.
     */
    public function duplicate(): self
    {
        $duplicate = $this->replicate();
        $duplicate->notes = ($this->notes ? $this->notes . ' ' : '') . '(Duplicate)';
        $duplicate->is_validated = false; // Reset validation status
        $duplicate->save();

        return $duplicate;
    }

    /**
     * Get summary information for display.
     */
    public function getSummaryAttribute(): array
    {
        return [
            'id' => $this->id,
            'gesture_name' => $this->gesture->label ?? 'Unknown',
            'confidence' => $this->confidence_percentage,
            'quality_level' => $this->quality_level,
            'quality_color' => $this->quality_color,
            'hand_count' => $this->hand_count,
            'is_validated' => $this->is_validated,
            'created_at' => $this->created_at->format('M j, Y H:i'),
            'notes' => $this->notes,
        ];
    }
}
