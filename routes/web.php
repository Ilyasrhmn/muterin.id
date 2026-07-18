<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\MotorcycleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TripController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('motorcycles', MotorcycleController::class);
    Route::post('motorcycles/{motorcycle}/activate', [MotorcycleController::class, 'activate'])
        ->name('motorcycles.activate');

    Route::post('maintenance_items/{item}/complete', [MaintenanceController::class, 'complete'])
        ->name('maintenance.complete');

    Route::get('riding', [TripController::class, 'create'])->name('riding');
    Route::post('trips', [TripController::class, 'store'])->name('trips.store');
});

require __DIR__.'/auth.php';
