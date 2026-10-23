<?php

use App\Http\Controllers\CentralStudentReportController;
use App\Http\Controllers\DevicePunchController;
use App\Http\Controllers\DeviceSyncPlanController;
use App\Http\Controllers\DeviceSyncReportController;
use App\Http\Controllers\QuestionBankClassesController;
use App\Http\Controllers\QuestionBankResultsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:120,1', 'auth.device'])->prefix('device')->group(function (): void {
    Route::post('/punches', DevicePunchController::class)->name('api.device.punches.store');
    Route::get('/sync-plan', DeviceSyncPlanController::class)->name('api.device.sync-plan');
    Route::post('/sync-report', DeviceSyncReportController::class)->name('api.device.sync-report');
});

Route::middleware(['throttle:30,1', 'auth.central'])->prefix('central')->group(function (): void {
    Route::get('/student-report', CentralStudentReportController::class)->name('api.central.student-report');
});

// Question Bank-এর অনুরোধ — এই স্কুলের সংযোগের secret দিয়ে সই করা।
Route::middleware(['throttle:60,1', 'auth.question-bank'])->prefix('question-bank')->group(function (): void {
    Route::get('/classes', QuestionBankClassesController::class)->name('api.question-bank.classes');
    Route::get('/classes/{class}/exams', [QuestionBankResultsController::class, 'exams'])->name('api.question-bank.exams');
    Route::get('/exams/{exam}/subjects/{subject}/students', [QuestionBankResultsController::class, 'sheet'])->name('api.question-bank.sheet');
    Route::post('/exams/{exam}/subjects/{subject}/results', [QuestionBankResultsController::class, 'store'])->name('api.question-bank.results');
});
