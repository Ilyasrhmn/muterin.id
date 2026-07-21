<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FuelController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MotorcycleController;
use App\Http\Controllers\OdometerReadingController;
use App\Http\Controllers\OtherExpenseController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TripController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : view('landing');
})->name('home');

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

    Route::post('odometer', [OdometerReadingController::class, 'store'])->name('odometer.store');

    Route::post('other-expenses', [OtherExpenseController::class, 'store'])->name('other-expenses.store');
    Route::delete('other-expenses/{otherExpense}', [OtherExpenseController::class, 'destroy'])->name('other-expenses.destroy');

    Route::get('riding', [TripController::class, 'create'])->name('riding');
    Route::post('trips/start', [TripController::class, 'start'])->name('trips.start');
    Route::patch('trips/{trip}/checkpoint', [TripController::class, 'checkpoint'])->name('trips.checkpoint');
    Route::patch('trips/{trip}/finish', [TripController::class, 'finish'])->name('trips.finish');
    Route::delete('trips/{trip}', [TripController::class, 'destroy'])->name('trips.destroy');

    Route::get('history', HistoryController::class)->name('history');
    Route::get('history/export', [HistoryController::class, 'exportPdf'])->name('history.export');

    Route::get('laporan', ReportController::class)->name('laporan');

    Route::get('bbm', [FuelController::class, 'index'])->name('bbm.index');
    Route::post('bbm', [FuelController::class, 'store'])->name('bbm.store');
    Route::delete('bbm/{fuelLog}', [FuelController::class, 'destroy'])->name('bbm.destroy');

    // Peta dipecah jadi tiga fitur terpisah
    Route::get('peta/rute', [MapController::class, 'routesPage'])->name('map.routes');
    Route::get('peta/titik', [MapController::class, 'pinsPage'])->name('map.pins');
    Route::get('peta/rencana', [MapController::class, 'plansPage'])->name('map.plans');

    Route::get('map/data', [MapController::class, 'data'])->name('map.data');
    Route::post('map/route', [MapController::class, 'previewRoute'])->name('map.route');
    Route::get('map/geocode/search', [MapController::class, 'geocodeSearch'])->name('map.geocode.search');
    Route::get('map/geocode/reverse', [MapController::class, 'geocodeReverse'])->name('map.geocode.reverse');
    Route::post('map/pins', [MapController::class, 'storePin'])->name('map.pins.store');
    Route::delete('map/pins/{pin}', [MapController::class, 'destroyPin'])->name('map.pins.destroy');
    Route::post('map/plans', [MapController::class, 'storePlan'])->name('map.plans.store');
    Route::delete('map/plans/{plan}', [MapController::class, 'destroyPlan'])->name('map.plans.destroy');
});

require __DIR__.'/auth.php';
