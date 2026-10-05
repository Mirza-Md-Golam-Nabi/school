<?php

use App\Http\Controllers\CentralStudentReportController;
use App\Http\Controllers\DevicePunchController;
use App\Http\Controllers\DeviceSyncPlanController;
use App\Http\Controllers\DeviceSyncReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:120,1', 'auth.device'])->prefix('device')->group(function (): void {
    Route::post('/punches', DevicePunchController::class)->name('api.device.punches.store');
    Route::get('/sync-plan', DeviceSyncPlanController::class)->name('api.device.sync-plan');
    Route::post('/sync-report', DeviceSyncReportController::class)->name('api.device.sync-report');
});

Route::middleware(['throttle:30,1', 'auth.central'])->prefix('central')->group(function (): void {
    Route::get('/student-report', CentralStudentReportController::class)->name('api.central.student-report');
});
