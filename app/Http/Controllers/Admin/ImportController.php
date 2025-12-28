<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gesture;
use App\Models\TrainingData;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ImportController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/ImportDataset');
    }

    public function store(Request $request)
    {
        $request->validate([
            'label' => 'required|string',
            'landmarks' => 'required|array',
            'confidence' => 'required|numeric',
        ]);

        // Normalize label (e.g., "A" -> "A", "Hello World" -> "Hello World")
        // We might want to use the folder name as the Label and a slugified version as the Name
        $label = $request->input('label');
        $name = Str::slug($label, '_');

        // Find or create the gesture
        $gesture = Gesture::firstOrCreate(
            ['name' => $name],
            [
                'label' => $label,
                'description' => 'Imported from dataset',
                'is_active' => true,
            ]
        );

        // Store training data
        TrainingData::create([
            'gesture_id' => $gesture->id,
            'landmark_data' => $request->input('landmarks'), // Raw landmarks
            'confidence_score' => $request->input('confidence'),
            'hand_count' => count($request->input('landmarks')), // Usually 1 hand per array in simple datasets, but structure depends on MediaPipe output
            'is_validated' => true,
            'notes' => 'Imported from external dataset',
        ]);

        return response()->json(['success' => true]);
    }
}
