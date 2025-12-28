<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Gesture;
use App\Models\TrainingData;

class GestureTrainingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/GestureTraining', [
            'gestures' => Gesture::withCount('trainingData')->get(),
        ]);
    }

    public function gestures(): JsonResponse
    {
        $gestures = Gesture::withCount('trainingData')->get();
        
        return response()->json($gestures);
    }

    public function storeGesture(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:gestures',
            'label' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $gesture = Gesture::create($request->only(['name', 'label', 'description']));

        return response()->json([
            'success' => true,
            'gesture' => $gesture,
        ]);
    }

    public function updateGesture(Request $request, Gesture $gesture): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:gestures,name,' . $gesture->id,
            'label' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $gesture->update($request->only(['name', 'label', 'description', 'is_active']));

        return response()->json([
            'success' => true,
            'gesture' => $gesture,
        ]);
    }

    public function deleteGesture(Gesture $gesture): JsonResponse
    {
        $gesture->delete();

        return response()->json([
            'success' => true,
            'message' => 'Gesture deleted successfully',
        ]);
    }

    public function storeTrainingData(Request $request): JsonResponse
    {
        $request->validate([
            'gesture_id' => 'required|exists:gestures,id',
            'landmark_data' => 'required|array',
            'confidence_score' => 'required|numeric|min:0|max:1',
        ]);

        $trainingData = TrainingData::create($request->only([
            'gesture_id',
            'landmark_data',
            'confidence_score'
        ]));

        // Update gesture training samples count and average landmark data
        $this->updateGestureModel($request->input('gesture_id'));

        return response()->json([
            'success' => true,
            'training_data' => $trainingData,
        ]);
    }

    public function getTrainingData(Gesture $gesture): JsonResponse
    {
        $trainingData = $gesture->trainingData()->latest()->get();
        
        return response()->json($trainingData);
    }

    public function deleteTrainingData(TrainingData $trainingData): JsonResponse
    {
        $gestureId = $trainingData->gesture_id;
        $trainingData->delete();

        // Update gesture model after deletion
        $this->updateGestureModel($gestureId);

        return response()->json([
            'success' => true,
            'message' => 'Training data deleted successfully',
        ]);
    }

    private function updateGestureModel(int $gestureId): void
    {
        $gesture = Gesture::find($gestureId);
        if (!$gesture) return;

        $trainingData = $gesture->trainingData()->get();
        $gesture->training_samples = $trainingData->count();

        if ($trainingData->isNotEmpty()) {
            // Calculate average landmark data from all training samples
            $averageLandmarks = $this->calculateAverageLandmarks($trainingData);
            $gesture->landmark_data = $averageLandmarks;
        } else {
            $gesture->landmark_data = null;
        }

        $gesture->save();
    }

    private function calculateAverageLandmarks($trainingData): array
    {
        if ($trainingData->isEmpty()) {
            return [];
        }

        $landmarkSums = [];
        $count = $trainingData->count();

        foreach ($trainingData as $data) {
            $landmarks = $data->landmark_data;
            
            foreach ($landmarks as $index => $landmark) {
                if (!isset($landmarkSums[$index])) {
                    $landmarkSums[$index] = ['x' => 0, 'y' => 0, 'z' => 0];
                }
                
                $landmarkSums[$index]['x'] += $landmark['x'] ?? 0;
                $landmarkSums[$index]['y'] += $landmark['y'] ?? 0;
                $landmarkSums[$index]['z'] += $landmark['z'] ?? 0;
            }
        }

        // Calculate averages
        $averageLandmarks = [];
        foreach ($landmarkSums as $index => $sum) {
            $averageLandmarks[$index] = [
                'x' => $sum['x'] / $count,
                'y' => $sum['y'] / $count,
                'z' => $sum['z'] / $count,
            ];
        }

        return $averageLandmarks;
    }
}
