<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gesture;
use App\Models\TrainingData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GestureManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Gesture::withCount('trainingData')
            ->orderBy('name'); // Keep it alphabetical for now

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('label', 'like', "%{$search}%");
            });
        }

        $gestures = $query->paginate(12)->onEachSide(1)->withQueryString();

        // Calculate stats (global, not just for the page)
        $stats = [
            'total' => Gesture::count(),
            'active' => Gesture::where('is_active', true)->count(),
            'totalSamples' => TrainingData::count(), // Assuming TrainingData model exists and is linked
        ];

        return Inertia::render('Admin/Gestures/Index', [
            'gestures' => $gestures,
            'stats' => $stats,
            'filters' => $request->only(['search'])
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Gestures/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:gestures',
            'label' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        Gesture::create($validated);

        return redirect()->route('admin.gestures.index')
            ->with('success', 'Gesture created successfully.');
    }

    public function edit(Gesture $gesture): Response
    {
        return Inertia::render('Admin/Gestures/Edit', [
            'gesture' => $gesture
        ]);
    }

    public function update(Request $request, Gesture $gesture)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:gestures,name,' . $gesture->id,
            'label' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $gesture->update($validated);

        return redirect()->route('admin.gestures.index')
            ->with('success', 'Gesture updated successfully.');
    }

    public function destroy(Gesture $gesture)
    {
        // Automatically delete associated training data (Cascade delete)
        // Note: Check your database schema. If 'onDelete set to cascade' is set in migration, this is automatic.
        // If not, we should manually delete them:
        $gesture->trainingData()->delete();

        $gesture->delete();

        return redirect()->route('admin.gestures.index')
            ->with('success', 'Gesture and its training data deleted successfully.');
    }

    public function toggleStatus(Gesture $gesture)
    {
        $gesture->is_active = !$gesture->is_active;
        $gesture->save();

        return redirect()->route('admin.gestures.index')
            ->with('success', 'Gesture status updated successfully.');
    }
}
