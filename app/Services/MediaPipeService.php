<?php

namespace App\Services;

use App\Models\Gesture;
use App\Models\TrainingData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class MediaPipeService
{
    /**
     * Detect gesture from landmark data
     */
    public function detectGesture(array $landmarkData, int $handCount = 1): ?array
    {
        try {
            // Normalize the landmark data
            $normalizedData = $this->normalizeLandmarks($landmarkData);
            
            // Get all active gestures that support the detected hand count
            $gestures = Gesture::where('is_active', true)
                ->where(function($query) use ($handCount) {
                    $query->where('supports_dual_hand', false)
                          ->orWhere(function($q) use ($handCount) {
                              $q->where('supports_dual_hand', true)
                                ->where('hand_count', $handCount);
                          });
                })
                ->get();

            if ($gestures->isEmpty()) {
                return null;
            }

            $bestMatch = null;
            $highestConfidence = 0;

            foreach ($gestures as $gesture) {
                $confidence = $this->calculateGestureConfidence($normalizedData, $gesture);
                
                if ($confidence > $highestConfidence && $confidence > 0.7) {
                    $highestConfidence = $confidence;
                    $bestMatch = [
                        'gesture' => $gesture,
                        'confidence' => $confidence,
                        'hand_count' => $handCount,
                        'landmarks' => $normalizedData
                    ];
                }
            }

            return $bestMatch;
        } catch (\Exception $e) {
            Log::error('Gesture detection error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Normalize landmark data for consistent comparison
     */
    public function normalizeLandmarks(array $landmarks): array
    {
        if (empty($landmarks)) {
            return [];
        }

        // Find wrist (landmark 0) as reference point
        $wrist = $landmarks[0] ?? null;
        if (!$wrist) {
            return $landmarks;
        }

        $normalized = [];
        
        foreach ($landmarks as $landmark) {
            $normalized[] = [
                'x' => $landmark['x'] - $wrist['x'],
                'y' => $landmark['y'] - $wrist['y'],
                'z' => $landmark['z'] - $wrist['z'],
            ];
        }

        // Scale to normalize hand size
        $scale = $this->calculateHandScale($normalized);
        if ($scale > 0) {
            foreach ($normalized as &$point) {
                $point['x'] /= $scale;
                $point['y'] /= $scale;
                $point['z'] /= $scale;
            }
        }

        return $normalized;
    }

    /**
     * Calculate confidence score for a gesture match
     */
    private function calculateGestureConfidence(array $normalizedData, Gesture $gesture): float
    {
        // Get training data for this gesture
        $trainingSamples = TrainingData::where('gesture_id', $gesture->id)
            ->where('confidence_score', '>', 0.8)
            ->get();

        if ($trainingSamples->isEmpty()) {
            return 0.5; // Default confidence if no training data
        }

        $totalConfidence = 0;
        $sampleCount = 0;

        foreach ($trainingSamples as $sample) {
            $sampleData = json_decode($sample->landmark_data, true);
            if ($sampleData) {
                $similarity = $this->calculateSimilarity($normalizedData, $sampleData);
                $totalConfidence += $similarity;
                $sampleCount++;
            }
        }

        return $sampleCount > 0 ? $totalConfidence / $sampleCount : 0.5;
    }

    /**
     * Calculate similarity between two sets of landmarks
     */
    private function calculateSimilarity(array $landmarks1, array $landmarks2): float
    {
        if (count($landmarks1) !== count($landmarks2) || count($landmarks1) === 0) {
            return 0;
        }

        $totalDistance = 0;
        $pointCount = count($landmarks1);

        for ($i = 0; $i < $pointCount; $i++) {
            $point1 = $landmarks1[$i];
            $point2 = $landmarks2[$i];
            
            $distance = sqrt(
                pow($point1['x'] - $point2['x'], 2) +
                pow($point1['y'] - $point2['y'], 2) +
                pow($point1['z'] - $point2['z'], 2)
            );
            
            $totalDistance += $distance;
        }

        $averageDistance = $totalDistance / $pointCount;
        
        // Convert distance to similarity (closer = higher similarity)
        $similarity = max(0, 1 - $averageDistance);
        
        return $similarity;
    }

    /**
     * Calculate hand scale for normalization
     */
    private function calculateHandScale(array $landmarks): float
    {
        if (count($landmarks) < 21) {
            return 1.0;
        }

        // Use distance from wrist to middle finger tip as scale reference
        $wrist = $landmarks[0];
        $middleTip = $landmarks[12];

        $distance = sqrt(
            pow($wrist['x'] - $middleTip['x'], 2) +
            pow($wrist['y'] - $middleTip['y'], 2) +
            pow($wrist['z'] - $middleTip['z'], 2)
        );

        return $distance > 0 ? $distance : 1.0;
    }

    /**
     * Validate landmark data quality
     */
    public function validateLandmarks(array $landmarks): bool
    {
        if (empty($landmarks) || count($landmarks) !== 21) {
            return false;
        }

        // Check if all required coordinates exist and are valid
        foreach ($landmarks as $landmark) {
            if (!isset($landmark['x']) || !isset($landmark['y']) || !isset($landmark['z'])) {
                return false;
            }
            
            if (!is_numeric($landmark['x']) || !is_numeric($landmark['y']) || !is_numeric($landmark['z'])) {
                return false;
            }
            
            // Check for reasonable coordinate ranges (MediaPipe uses normalized coordinates 0-1)
            if ($landmark['x'] < -0.5 || $landmark['x'] > 1.5 ||
                $landmark['y'] < -0.5 || $landmark['y'] > 1.5 ||
                $landmark['z'] < -1 || $landmark['z'] > 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Process dual-hand landmarks
     */
    public function processDualHands(array $leftHand, array $rightHand): ?array
    {
        if (!$this->validateLandmarks($leftHand) || !$this->validateLandmarks($rightHand)) {
            return null;
        }

        // Normalize both hands
        $normalizedLeft = $this->normalizeLandmarks($leftHand);
        $normalizedRight = $this->normalizeLandmarks($rightHand);

        // Combine landmarks for dual-hand gestures
        $combinedLandmarks = [
            'left' => $normalizedLeft,
            'right' => $normalizedRight
        ];

        // Detect gesture with dual-hand support
        $gestures = Gesture::where('is_active', true)
            ->where('supports_dual_hand', true)
            ->get();

        $bestMatch = null;
        $highestConfidence = 0;

        foreach ($gestures as $gesture) {
            $confidence = $this->calculateDualHandConfidence($combinedLandmarks, $gesture);
            
            if ($confidence > $highestConfidence && $confidence > 0.7) {
                $highestConfidence = $confidence;
                $bestMatch = [
                    'gesture' => $gesture,
                    'confidence' => $confidence,
                    'hand_count' => 2,
                    'landmarks' => $combinedLandmarks
                ];
            }
        }

        return $bestMatch;
    }

    /**
     * Calculate confidence for dual-hand gestures
     */
    private function calculateDualHandConfidence(array $combinedLandmarks, Gesture $gesture): float
    {
        $trainingSamples = TrainingData::where('gesture_id', $gesture->id)
            ->where('hand_count', 2)
            ->where('confidence_score', '>', 0.8)
            ->get();

        if ($trainingSamples->isEmpty()) {
            return 0.5;
        }

        $totalConfidence = 0;
        $sampleCount = 0;

        foreach ($trainingSamples as $sample) {
            $sampleData = json_decode($sample->landmark_data, true);
            if ($sampleData && isset($sampleData['left']) && isset($sampleData['right'])) {
                $leftSimilarity = $this->calculateSimilarity($combinedLandmarks['left'], $sampleData['left']);
                $rightSimilarity = $this->calculateSimilarity($combinedLandmarks['right'], $sampleData['right']);
                $averageSimilarity = ($leftSimilarity + $rightSimilarity) / 2;
                
                $totalConfidence += $averageSimilarity;
                $sampleCount++;
            }
        }

        return $sampleCount > 0 ? $totalConfidence / $sampleCount : 0.5;
    }

    /**
     * Get gesture statistics
     */
    public function getGestureStatistics(): array
    {
        $totalGestures = Gesture::count();
        $activeGestures = Gesture::where('is_active', true)->count();
        $dualHandGestures = Gesture::where('supports_dual_hand', true)->count();
        $totalTrainingSamples = TrainingData::count();

        return [
            'total_gestures' => $totalGestures,
            'active_gestures' => $activeGestures,
            'dual_hand_gestures' => $dualHandGestures,
            'total_training_samples' => $totalTrainingSamples,
            'average_samples_per_gesture' => $totalGestures > 0 ? round($totalTrainingSamples / $totalGestures, 2) : 0
        ];
    }
}
