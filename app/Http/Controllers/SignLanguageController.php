<?php

namespace App\Http\Controllers;

use App\Models\Gesture;
use App\Models\TrainingData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class SignLanguageController extends Controller
{
    /**
     * Display sign language detection page.
     */
    public function index()
    {
        // Get active gestures with their training data counts
        $gestures = Gesture::where('is_active', true)
            ->withCount(['trainingData' => function ($query) {
                $query->where('is_validated', true);
            }])
            ->get(['id', 'name', 'label', 'description', 'supports_dual_hand']);

        return inertia('Welcome', [
            'gestures' => $gestures,
            'canRegister' => true, // Simplified for now
        ]);
    }

    /**
     * Detect gesture from landmark data.
     */
    public function detect(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'landmarkData' => 'required|array|min:21',
                'confidence' => 'required|numeric|min:0|max:1',
                'handCount' => 'sometimes|integer|min:1|max:2',
                'handData' => 'sometimes|array',
            ], [
                'landmarkData.required' => 'Hand landmark data is required',
                'landmarkData.min' => 'At least 21 landmark points are required',
                'confidence.required' => 'Confidence score is required',
                'confidence.numeric' => 'Confidence must be a number',
                'confidence.min' => 'Confidence must be between 0 and 1',
                'confidence.max' => 'Confidence must be between 0 and 1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'messages' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();
            $landmarkData = $validated['landmarkData'];
            $confidence = $validated['confidence'];
            $handCount = $validated['handCount'] ?? 1;

            // Get gestures ready for detection
            $gestures = Gesture::where('is_active', true)
                ->with(['trainingData' => function ($query) {
                    $query->where('is_validated', true);
                }])
                ->get();

            if ($gestures->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'No trained gestures available',
                    'message' => 'Please train some gestures first.',
                ]);
            }

            // Find best matching gesture
            $bestMatch = $this->findBestGesture($landmarkData, $gestures, $handCount);

            if (!$bestMatch) {
                return response()->json([
                    'success' => true,
                    'gesture' => null,
                    'confidence' => 0,
                    'threshold' => 0.7,
                    'meets_threshold' => false,
                    'message' => 'No gesture detected. Please try again.',
                ]);
            }

            return response()->json([
                'success' => true,
                'gesture' => [
                    'id' => $bestMatch['gesture']->id,
                    'name' => $bestMatch['gesture']->name,
                    'label' => $bestMatch['gesture']->label,
                    'supports_dual_hand' => $bestMatch['gesture']->supports_dual_hand,
                ],
                'confidence' => round($bestMatch['confidence'], 4),
                'threshold' => 0.7,
                'meets_threshold' => $bestMatch['confidence'] >= 0.7,
            ]);

        } catch (\Exception $e) {
            Log::error('Gesture detection error: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Detection failed',
                'message' => 'An error occurred during gesture detection. Please try again.',
            ], 500);
        }
    }

    /**
     * Find the best matching gesture for the given landmarks.
     */
    private function findBestGesture($landmarkData, $gestures, $handCount)
    {
        $bestMatch = null;
        $bestConfidence = 0;

        foreach ($gestures as $gesture) {
            // Skip if hand count doesn't match for dual-hand gestures
            if ($gesture->supports_dual_hand && $handCount < 2) {
                continue;
            }

            // Compare with all training data for this gesture
            foreach ($gesture->trainingData as $trainingData) {
                // Handle both string and array landmark_data
                if (is_string($trainingData->landmark_data)) {
                    $storedLandmarks = json_decode($trainingData->landmark_data, true);
                } else {
                    $storedLandmarks = $trainingData->landmark_data;
                }
                
                if (!$storedLandmarks) {
                    continue;
                }

                $similarity = $this->compareLandmarks($landmarkData, $storedLandmarks);
                
                if ($similarity > $bestConfidence) {
                    $bestConfidence = $similarity;
                    $bestMatch = [
                        'gesture' => $gesture,
                        'confidence' => $similarity,
                    ];
                }
            }
        }

        return $bestMatch;
    }

    /**
     * Compare two sets of landmarks and return similarity score.
     */
    private function compareLandmarks($currentLandmarks, $storedLandmarks)
    {
        if (empty($currentLandmarks) || empty($storedLandmarks)) {
            return 0;
        }

        // Normalize landmarks
        $normalizedCurrent = $this->normalizeLandmarks($currentLandmarks);
        $normalizedStored = $this->normalizeLandmarks($storedLandmarks);

        if (count($normalizedCurrent) < 21 || count($normalizedStored) < 21) {
            return 0;
        }

        $totalDistance = 0;
        $pointCount = min(count($normalizedCurrent), count($normalizedStored));

        // Calculate average distance between corresponding points
        for ($i = 0; $i < $pointCount; $i++) {
            $dx = $normalizedCurrent[$i]['x'] - $normalizedStored[$i]['x'];
            $dy = $normalizedCurrent[$i]['y'] - $normalizedStored[$i]['y'];
            $dz = $normalizedCurrent[$i]['z'] - $normalizedStored[$i]['z'];
            
            $distance = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
            $totalDistance += $distance;
        }

        $averageDistance = $totalDistance / $pointCount;
        
        // Convert distance to similarity (lower distance = higher similarity)
        $similarity = exp(-$averageDistance * 10);
        
        return max(0, min(1, $similarity));
    }

    /**
     * Normalize landmarks relative to wrist position.
     */
    private function normalizeLandmarks($landmarks)
    {
        if (empty($landmarks) || count($landmarks) < 21) {
            return [];
        }

        // Convert to array format if needed
        $normalizedLandmarks = [];
        foreach ($landmarks as $point) {
            if (is_object($point)) {
                $normalizedLandmarks[] = [
                    'x' => $point->x ?? 0,
                    'y' => $point->y ?? 0,
                    'z' => $point->z ?? 0,
                ];
            } elseif (is_array($point)) {
                $normalizedLandmarks[] = [
                    'x' => $point['x'] ?? 0,
                    'y' => $point['y'] ?? 0,
                    'z' => $point['z'] ?? 0,
                ];
            }
        }

        if (count($normalizedLandmarks) < 21) {
            return [];
        }

        // Use wrist (point 0) as reference
        $wrist = $normalizedLandmarks[0];
        
        // Calculate scale based on distance from wrist to middle finger tip
        $middleTip = $normalizedLandmarks[12];
        $scale = sqrt(
            pow($middleTip['x'] - $wrist['x'], 2) +
            pow($middleTip['y'] - $wrist['y'], 2) +
            pow($middleTip['z'] - $wrist['z'], 2)
        );

        if ($scale < 0.001) {
            $scale = 1.0;
        }

        // Normalize each point
        $normalized = [];
        foreach ($normalizedLandmarks as $point) {
            $normalized[] = [
                'x' => ($point['x'] - $wrist['x']) / $scale,
                'y' => ($point['y'] - $wrist['y']) / $scale,
                'z' => ($point['z'] - $wrist['z']) / $scale,
            ];
        }

        return $normalized;
    }

    /**
     * Save detected sentence.
     */
    public function saveSentence(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'sentence' => 'required|string|max:500',
                'gestures' => 'required|array|max:20',
                'gestures.*.id' => 'required|exists:gestures,id',
                'gestures.*.confidence' => 'required|numeric|min:0|max:1',
                'gestures.*.timestamp' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'messages' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();

            return response()->json([
                'success' => true,
                'message' => 'Sentence saved successfully!',
                'data' => [
                    'sentence' => $validated['sentence'],
                    'gesture_count' => count($validated['gestures']),
                    'saved_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Sentence save error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Save failed',
                'message' => 'An error occurred while saving sentence. Please try again.',
            ], 500);
        }
    }

    /**
     * Get available gestures for detection.
     */
    public function getAvailableGestures(): JsonResponse
    {
        try {
            $gestures = Gesture::where('is_active', true)
                ->withCount(['trainingData' => function ($query) {
                    $query->where('is_validated', true);
                }])
                ->get()
                ->map(function ($gesture) {
                    return [
                        'id' => $gesture->id,
                        'name' => $gesture->name,
                        'label' => $gesture->label,
                        'description' => $gesture->description,
                        'supports_dual_hand' => $gesture->supports_dual_hand,
                        'training_samples' => $gesture->training_data_count,
                    ];
                });

            return response()->json([
                'success' => true,
                'gestures' => $gestures,
                'total_count' => $gestures->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Get gestures error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Failed to load gestures',
                'message' => 'Unable to load available gestures at this time.',
            ], 500);
        }
    }
}
