<?php

use App\Http\Controllers\AdmitCardPdfController;
use App\Http\Controllers\EmailVerificationController;
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
