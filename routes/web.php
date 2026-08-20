<?php

use App\Http\Controllers\AdmitCardPdfController;
use App\Http\Controllers\ClassMarksheetsPdfController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\FundTransactionAttachmentController;
use App\Http\Controllers\MarksheetPdfController;
use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');

Route::get('/admit-cards/{admitCard}/view', AdmitCardPdfController::class)
    ->middleware('auth')
    ->name('admit-cards.view');

Route::get('/marksheets/{marksheet}/view', MarksheetPdfController::class)
    ->middleware('auth')
    ->name('marksheets.view');

Route::get('/classes/{class}/marksheets/{exam}/download', ClassMarksheetsPdfController::class)
    ->middleware('auth')
    ->name('marksheets.class.download');

// No 'auth' middleware here — this app has no generic named 'login' route (Filament panels
// each have their own), so the default guest-redirect would throw RouteNotFoundException.
// The controller itself checks auth()->check() and aborts 401 instead.
Route::get('/fund-transactions/{fundTransaction}/attachment', FundTransactionAttachmentController::class)
    ->name('fund-transactions.attachment');

// No 'auth' middleware here either, for the same reason as above — the Form
// Requests' authorize() checks auth()->check() and returns 403 instead.
Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])
    ->name('push-subscriptions.store');

Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])
    ->name('push-subscriptions.destroy');
