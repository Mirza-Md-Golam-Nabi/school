<?php

use App\Http\Controllers\AdmitCardPdfController;
use App\Http\Controllers\ClassAdmitCardsPdfController;
use App\Http\Controllers\ClassAttendanceReportPdfController;
use App\Http\Controllers\ClassMarksheetsPdfController;
use App\Http\Controllers\ClassSeatPlanPdfController;
use App\Http\Controllers\ClassStudentListPdfController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\ExamSchedulePdfController;
use App\Http\Controllers\ExamTabulationSheetPdfController;
use App\Http\Controllers\FeePaymentSlipPdfController;
use App\Http\Controllers\FundTransactionAttachmentController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MarksheetPdfController;
use App\Http\Controllers\PushNotificationDeliveryController;
use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');

Route::get('/locale/{locale}', [LocaleController::class, 'switch'])
    ->name('locale.switch');

Route::get('/admit-cards/{admitCard}/view', AdmitCardPdfController::class)
    ->middleware('auth')
    ->name('admit-cards.view');

Route::get('/marksheets/{marksheet}/view', MarksheetPdfController::class)
    ->middleware('auth')
    ->name('marksheets.view');

Route::get('/classes/{class}/marksheets/{exam}/download', ClassMarksheetsPdfController::class)
    ->middleware('auth')
    ->name('marksheets.class.download');

Route::get('/classes/{class}/admit-cards/{exam}/download', ClassAdmitCardsPdfController::class)
    ->middleware('auth')
    ->name('admit-cards.class.download');

Route::get('/classes/{class}/seat-plan/download', ClassSeatPlanPdfController::class)
    ->middleware('auth')
    ->name('seat-plan.class.download');

Route::get('/classes/{class}/student-list/download', ClassStudentListPdfController::class)
    ->middleware('auth')
    ->name('student-list.class.download');

Route::get('/classes/{class}/attendance-report/{year}/{month}/download', ClassAttendanceReportPdfController::class)
    ->middleware('auth')
    ->whereNumber(['year', 'month'])
    ->name('attendance-report.class.download');

Route::get('/exams/{exam}/schedule/download', ExamSchedulePdfController::class)
    ->middleware('auth')
    ->name('exams.schedule.download');

Route::get('/exams/{exam}/tabulation-sheet/download', ExamTabulationSheetPdfController::class)
    ->middleware('auth')
    ->name('exams.tabulation-sheet.download');

Route::get('/fee-payments/{batchId}/slip', FeePaymentSlipPdfController::class)
    ->middleware('auth')
    ->name('fee-payments.slip.download');

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

Route::post('/push-notification-deliveries/acknowledge', [PushNotificationDeliveryController::class, 'acknowledge'])
    ->middleware('throttle:60,1')
    ->name('push-notification-deliveries.acknowledge');
