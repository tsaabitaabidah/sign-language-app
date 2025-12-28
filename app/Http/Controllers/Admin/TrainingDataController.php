<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gesture;
use App\Models\TrainingData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TrainingDataController extends Controller
{
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $gestures = Gesture::where('is_active', true)->orderBy('name')->get();
        
        return Inertia::render('Admin/TrainingDataCreate', [
            'gestures' => $gestures,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(TrainingData $trainingData)
    {
        $trainingData->load('gesture');
        
        return Inertia::render('Admin/TrainingDataShow', [
            'trainingData' => $trainingData,
        ]);
    }

    /**
     * Display the training data management page.
     */
    public function index(Request $request): Response
    {
        $gestures = Gesture::withCount(['trainingData' => function ($query) {
            $query->where('is_validated', true);
        }])
        ->orderBy('name')
        ->get();

        $query = TrainingData::with('gesture')
            ->where('is_validated', true)
            ->latest();

        // Apply filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->whereHas('gesture', function($g) use ($search) {
                    $g->where('label', 'like', "%{$search}%");
                })
                ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($request->filled('gesture_id')) {
            $query->where('gesture_id', $request->input('gesture_id'));
        }

        if ($request->filled('hand_count')) {
            $query->where('hand_count', $request->input('hand_count'));
        }

        if ($request->filled('quality')) {
            $quality = $request->input('quality');
            if ($quality === 'high') {
                $query->where('confidence_score', '>=', 0.8);
            } elseif ($quality === 'medium') {
                $query->whereBetween('confidence_score', [0.6, 0.799]);
            } elseif ($quality === 'low') {
                $query->where('confidence_score', '<', 0.6);
            }
        }

        $trainingData = $query->paginate(12)->onEachSide(1)->withQueryString();

        return inertia('Admin/TrainingData', [
            'gestures' => $gestures,
            'trainingData' => $trainingData, // Renamed from recentTrainingData
            'filters' => $request->only(['search', 'gesture_id', 'hand_count', 'quality']),
        ]);
    }

    /**
     * Store new training data.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'gesture_id' => 'required|exists:gestures,id',
                'landmark_data' => 'required|array|min:21',
                'confidence_score' => 'required|numeric|min:0|max:1',
                'hand_count' => 'required|integer|min:1|max:2',
                'hand_data' => 'sometimes|array',
                'notes' => 'nullable|string|max:500',
                'metadata' => 'sometimes|array',
            ], [
                'gesture_id.required' => 'Please select a gesture',
                'gesture_id.exists' => 'Selected gesture does not exist',
                'landmark_data.required' => 'Hand landmark data is required',
                'landmark_data.min' => 'At least 21 landmark points are required',
                'confidence_score.required' => 'Confidence score is required',
                'confidence_score.numeric' => 'Confidence must be a number',
                'confidence_score.min' => 'Confidence must be between 0 and 1',
                'confidence_score.max' => 'Confidence must be between 0 and 1',
                'hand_count.required' => 'Hand count is required',
                'hand_count.integer' => 'Hand count must be an integer',
                'hand_count.min' => 'Hand count must be at least 1',
                'hand_count.max' => 'Hand count cannot exceed 2',
            ]);

            if ($validator->fails()) {
                throw ValidationException::withMessages($validator->errors()->toArray());
            }

            $validated = $validator->validated();
            
            // Get gesture for validation
            $gesture = Gesture::findOrFail($validated['gesture_id']);
            
            // Validate hand count matches gesture requirements
            if ($gesture->supports_dual_hand && $validated['hand_count'] < 2) {
                throw ValidationException::withMessages([
                    'hand_count' => 'This gesture requires dual hands. Please use both hands when capturing this gesture.'
                ]);
            }

            // Create training data
            $trainingData = TrainingData::create([
                'gesture_id' => $validated['gesture_id'],
                'landmark_data' => $validated['landmark_data'],
                'normalized_data' => $this->normalizeLandmarks($validated['landmark_data']),
                'confidence_score' => $validated['confidence_score'],
                'hand_count' => $validated['hand_count'],
                'hand_data' => $validated['hand_data'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'metadata' => array_merge($validated['metadata'] ?? [], [
                    'captured_at' => now()->toISOString(),
                    'user_agent' => $request->userAgent(),
                    'ip_address' => $request->ip(),
                ]),
                'is_validated' => true, // Auto-validate for admin
            ]);

            // Redirect back with success message for Inertia
            return redirect()->back()
                ->with('success', "Training data for '{$gesture->name}' saved successfully!");

        } catch (ValidationException $e) {
            // Inertia will handle validation errors automatically
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Training data save error: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'An error occurred while saving training data. Please try again.');
        }
    }

    /**
     * Get training data for a specific gesture.
     */
    public function getGestureTrainingData(Gesture $gesture): JsonResponse
    {
        try {
            $trainingData = $gesture->trainingData()
                ->where('is_validated', true)
                ->latest()
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $trainingData->items(),
                'pagination' => [
                    'current_page' => $trainingData->currentPage(),
                    'last_page' => $trainingData->lastPage(),
                    'per_page' => $trainingData->perPage(),
                    'total' => $trainingData->total(),
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Get training data error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Failed to load training data',
                'message' => 'Unable to load training data at this time.',
            ], 500);
        }
    }

    /**
     * Delete training data.
     */
    public function destroy(TrainingData $trainingData): JsonResponse
    {
        try {
            $gestureName = $trainingData->gesture->name;
            $trainingData->delete();

            return response()->json([
                'success' => true,
                'message' => "Training data for '{$gestureName}' deleted successfully!",
            ]);

        } catch (\Exception $e) {
            \Log::error('Training data delete error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Delete failed',
                'message' => 'An error occurred while deleting training data.',
            ], 500);
        }
    }

    /**
     * Get training statistics.
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $stats = [
                'total_gestures' => Gesture::count(),
                'total_training_data' => TrainingData::where('is_validated', true)->count(),
                'single_hand_data' => TrainingData::where('is_validated', true)->where('hand_count', 1)->count(),
                'dual_hand_data' => TrainingData::where('is_validated', true)->where('hand_count', 2)->count(),
                'average_confidence' => TrainingData::where('is_validated', true)->avg('confidence_score'),
                'high_quality_data' => TrainingData::where('is_validated', true)->where('confidence_score', '>=', 0.8)->count(),
                'recent_captures' => TrainingData::where('is_validated', true)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),
            ];

            // Gesture-specific stats
            $gestureStats = Gesture::withCount(['trainingData' => function ($query) {
                $query->where('is_validated', true);
            }])
            ->with(['trainingData' => function ($query) {
                $query->where('is_validated', true);
            }])
            ->get()
            ->map(function ($gesture) {
                return [
                    'id' => $gesture->id,
                    'name' => $gesture->name,
                    'label' => $gesture->label,
                    'training_count' => $gesture->training_data_count,
                    'average_confidence' => $gesture->trainingData->avg('confidence_score') ?? 0,
                    'supports_dual_hand' => $gesture->supports_dual_hand,
                    'last_trained' => $gesture->trainingData->max('created_at'),
                ];
            });

            return response()->json([
                'success' => true,
                'stats' => $stats,
                'gesture_stats' => $gestureStats,
            ]);

        } catch (\Exception $e) {
            \Log::error('Get statistics error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Failed to load statistics',
                'message' => 'Unable to load statistics at this time.',
            ], 500);
        }
    }

    /**
     * Export training data.
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'gesture_id' => 'sometimes|exists:gestures,id',
                'format' => 'sometimes|in:json,csv',
                'hand_count' => 'sometimes|integer|min:1|max:2',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid export parameters',
                    'messages' => $validator->errors(),
                ], 422);
            }

            $query = TrainingData::with('gesture')->where('is_validated', true);

            if ($request->has('gesture_id')) {
                $query->where('gesture_id', $request->gesture_id);
            }

            if ($request->has('hand_count')) {
                $query->where('hand_count', $request->hand_count);
            }

            $trainingData = $query->latest()->get();

            $format = $request->get('format', 'json');
            
            if ($format === 'csv') {
                $csv = $this->convertToCSV($trainingData);
                $filename = 'training_data_' . date('Y-m-d_H-i-s') . '.csv';
                
                return response($csv)
                    ->header('Content-Type', 'text/csv')
                    ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
            }

            return response()->json([
                'success' => true,
                'data' => $trainingData,
                'exported_at' => now()->toISOString(),
                'count' => $trainingData->count(),
            ]);

        } catch (\Exception $e) {
            \Log::error('Export error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Export failed',
                'message' => 'An error occurred during export.',
            ], 500);
        }
    }

    /**
     * Normalize landmarks for comparison.
     */
    private function normalizeLandmarks(array $landmarks): array
    {
        if (empty($landmarks)) {
            return [];
        }

        // Get wrist position as reference
        $wrist = $landmarks[0];
        
        // Calculate bounding box
        $minX = $maxX = $landmarks[0]['x'] ?? 0;
        $minY = $maxY = $landmarks[0]['y'] ?? 0;
        $minZ = $maxZ = $landmarks[0]['z'] ?? 0;
        
        foreach ($landmarks as $point) {
            $x = $point['x'] ?? 0;
            $y = $point['y'] ?? 0;
            $z = $point['z'] ?? 0;
            
            $minX = min($minX, $x);
            $maxX = max($maxX, $x);
            $minY = min($minY, $y);
            $maxY = max($maxY, $y);
            $minZ = min($minZ, $z);
            $maxZ = max($maxZ, $z);
        }
        
        // Calculate scale
        $rangeX = $maxX - $minX;
        $rangeY = $maxY - $minY;
        $rangeZ = $maxZ - $minZ;
        $scale = max($rangeX, $rangeY, $rangeZ);
        
        if ($scale < 0.001) {
            $scale = 1.0;
        }
        
        // Normalize each point
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
     * Convert training data to CSV format.
     */
    private function convertToCSV($trainingData): string
    {
        $csv = "ID,Gesture,Label,Hand Count,Confidence,Notes,Created At\n";
        
        foreach ($trainingData as $data) {
            $csv .= sprintf(
                "%d,%s,%s,%d,%.4f,\"%s\",%s\n",
                $data->id,
                $data->gesture->name,
                $data->gesture->label,
                $data->hand_count,
                $data->confidence_score,
                str_replace('"', '""', $data->notes ?? ''),
                $data->created_at->toISOString()
            );
        }
        
        return $csv;
    }
}
