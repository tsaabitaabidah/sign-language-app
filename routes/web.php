<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Controllers\SignLanguageController;
use App\Http\Controllers\Admin\GestureTrainingController;
use App\Http\Controllers\Admin\GestureManagementController;
use App\Http\Controllers\Admin\TrainingDataController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ImportController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Sign Language Detection Routes
Route::get('/sign-language', [SignLanguageController::class, 'index'])->name('sign-language.index');
Route::post('/sign-language/detect', [SignLanguageController::class, 'detect'])->name('sign-language.detect');
Route::post('/sign-language/save-sentence', [SignLanguageController::class, 'saveSentence'])->name('sign-language.save-sentence');

// Admin Routes for Gesture Training
Route::prefix('admin')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/gesture-training', [GestureTrainingController::class, 'index'])->name('admin.gesture-training.index');
    Route::post('/gesture-training/capture', [GestureTrainingController::class, 'capture'])->name('admin.gesture-training.capture');
    Route::post('/gesture-training/train', [GestureTrainingController::class, 'train'])->name('admin.gesture-training.train');
    Route::get('/gesture-training/status', [GestureTrainingController::class, 'status'])->name('admin.gesture-training.status');
    
    // Gesture Management Routes
    Route::get('/gestures', [GestureManagementController::class, 'index'])->name('admin.gestures.index');
    Route::get('/gestures/create', [GestureManagementController::class, 'create'])->name('admin.gestures.create');
    Route::post('/gestures', [GestureManagementController::class, 'store'])->name('admin.gestures.store');
    Route::get('/gestures/{gesture}/edit', [GestureManagementController::class, 'edit'])->name('admin.gestures.edit');
    Route::put('/gestures/{gesture}', [GestureManagementController::class, 'update'])->name('admin.gestures.update');
    Route::delete('/gestures/{gesture}', [GestureManagementController::class, 'destroy'])->name('admin.gestures.destroy');
    Route::patch('/gestures/{gesture}/toggle', [GestureManagementController::class, 'toggleStatus'])->name('admin.gestures.toggle');
    
    // Training Data Management Routes
    Route::get('/training-data', [TrainingDataController::class, 'index'])->name('admin.training-data.index');
    Route::get('/training-data/create', [TrainingDataController::class, 'create'])->name('admin.training-data.create');
    Route::post('/training-data', [TrainingDataController::class, 'store'])->name('admin.training-data.store');
    Route::get('/training-data/{trainingData}', [TrainingDataController::class, 'show'])->name('admin.training-data.show');
    Route::get('/training-data/{trainingData}/edit', [TrainingDataController::class, 'edit'])->name('admin.training-data.edit');
    Route::put('/training-data/{trainingData}', [TrainingDataController::class, 'update'])->name('admin.training-data.update');
    Route::delete('/training-data/{trainingData}', [TrainingDataController::class, 'destroy'])->name('admin.training-data.destroy');
    Route::post('/training-data/bulk-delete', [TrainingDataController::class, 'bulkDelete'])->name('admin.training-data.bulk-delete');
    Route::get('/training-data/export', [TrainingDataController::class, 'export'])->name('admin.training-data.export');
    
    // User Management Routes
    Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('admin.users.create');
    Route::post('/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('admin.users.show');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    Route::patch('/users/{user}/toggle-verification', [UserController::class, 'toggleVerification'])->name('admin.users.toggle-verification');
    Route::get('/api/users', [UserController::class, 'getUsers'])->name('admin.users.api');
    Route::post('/users/bulk-delete', [UserController::class, 'bulkDelete'])->name('admin.users.bulk-delete');

    // Import Dataset Routes
    Route::get('/import-dataset', [ImportController::class, 'index'])->name('admin.import.index');
    Route::post('/import-data', [ImportController::class, 'store'])->name('admin.import.store');
});

require __DIR__.'/settings.php';
