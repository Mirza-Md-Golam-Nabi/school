<?php

use App\Http\Controllers\AdmitCardPdfController;
use App\Http\Controllers\AdmitCardSamplePdfController;
use App\Http\Controllers\ClassAdmitCardsPdfController;
use App\Http\Controllers\ClassAttendanceReportPdfController;
use App\Http\Controllers\ClassMarksheetsPdfController;
use App\Http\Controllers\ClassSeatPlanPdfController;
use App\Http\Controllers\ClassStudentIdCardsPdfController;
use App\Http\Controllers\ClassStudentListPdfController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\EmployeeAttendanceReportPdfController;
use App\Http\Controllers\ExamResultReportPdfController;
use App\Http\Controllers\ExamSchedulePdfController;
use App\Http\Controllers\ExamTabulationSheetPdfController;
use App\Http\Controllers\FeePaymentSlipPdfController;
use App\Http\Controllers\FundTransactionAttachmentController;
use App\Http\Controllers\InstallGuideController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MarksheetPdfController;
use App\Http\Controllers\PushNotificationDeliveryController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\StaffIdCardsPdfController;
use App\Http\Controllers\SubjectListPdfController;
use App\Http\Controllers\TeacherAttendanceSheetPdfController;
use App\Http\Controllers\TeacherIdCardsPdfController;
use App\Http\Controllers\TeacherListPdfController;
use App\Http\Controllers\VerifyAdmitCardController;
use App\Http\Controllers\VerifyDocumentController;
use App\Http\Controllers\VerifyIdCardController;
use App\Http\Controllers\VerifyMarksheetController;
use App\Models\SchoolSetting;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    $logo = SchoolSetting::get('school_logo');

    return view('welcome', [
        'schoolName' => (string) config('app.school_name'),
        'schoolAddress' => (string) SchoolSetting::get('school_address', ''),
        'schoolLogoUrl' => filled($logo) ? Storage::disk('public')->url($logo) : null,
        'establishedYear' => SchoolSetting::get('school_established_year'),
        'eiinLine' => SchoolSetting::eiinLine(),
    ]);
})->name('home');

// Kept inside each panel's path (= the PWA manifest scope) so the browser
// can offer its native install prompt on the guide page as well.
Route::get('/{panel}/install', InstallGuideController::class)
    ->whereIn('panel', ['student', 'teacher'])
    ->name('install-guide');

// Opened by the QR code printed on admit cards: public (exam-hall invigilators
// scan it without logging in) and throttled so tokens can't be brute-forced.
Route::get('/verify/admit-card/{token}', VerifyAdmitCardController::class)
    ->where('token', '[A-Za-z0-9]{1,32}')
    ->middleware('throttle:30,1')
    ->name('admit-cards.verify');

// Opened by the QR code printed on marksheets, and the page where the code
// printed under it can be typed in by hand — public and throttled the same way.
// Opened by the QR code printed on student, teacher and staff ID cards — public
// (a gatekeeper or parent scans it without logging in) and throttled the same way.
Route::get('/verify/id-card/{token}', VerifyIdCardController::class)
    ->where('token', '[A-Za-z0-9]{1,32}')
    ->middleware('throttle:30,1')
    ->name('id-cards.verify');

Route::get('/verify', VerifyDocumentController::class)
    ->middleware('throttle:30,1')
    ->name('verify');

Route::get('/verify/marksheet/{code}', VerifyMarksheetController::class)
    ->where('code', '[A-Za-z0-9-]{1,20}')
    ->middleware('throttle:30,1')
    ->name('marksheets.verify');

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');

Route::get('/locale/{locale}', [LocaleController::class, 'switch'])
    ->name('locale.switch');

Route::get('/admit-cards/sample/{design}', AdmitCardSamplePdfController::class)
    ->middleware('auth')
    ->name('admit-cards.sample');

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

Route::get('/classes/{class}/student-id-cards/view', [ClassStudentIdCardsPdfController::class, 'view'])
    ->middleware('auth')
    ->name('student-id-cards.class.view');

Route::get('/classes/{class}/student-id-cards/download', [ClassStudentIdCardsPdfController::class, 'download'])
    ->middleware('auth')
    ->name('student-id-cards.class.download');

Route::get('/classes/{class}/student-list/download', ClassStudentListPdfController::class)
    ->middleware('auth')
    ->name('student-list.class.download');

Route::get('/subject-list/download', SubjectListPdfController::class)
    ->middleware('auth')
    ->name('subject-list.download');

Route::get('/teacher-list/download', TeacherListPdfController::class)
    ->middleware('auth')
    ->name('teacher-list.download');

Route::get('/teacher-id-cards/view', [TeacherIdCardsPdfController::class, 'view'])
    ->middleware('auth')
    ->name('teacher-id-cards.view');

Route::get('/teacher-id-cards/download', [TeacherIdCardsPdfController::class, 'download'])
    ->middleware('auth')
    ->name('teacher-id-cards.download');

Route::get('/teacher-id-cards/{teacher}/view', [TeacherIdCardsPdfController::class, 'show'])
    ->middleware('auth')
    ->name('teacher-id-cards.teacher.view');

Route::get('/staff-id-cards/view', [StaffIdCardsPdfController::class, 'view'])
    ->middleware('auth')
    ->name('staff-id-cards.view');

Route::get('/staff-id-cards/download', [StaffIdCardsPdfController::class, 'download'])
    ->middleware('auth')
    ->name('staff-id-cards.download');

Route::get('/staff-id-cards/{staff}/view', [StaffIdCardsPdfController::class, 'show'])
    ->middleware('auth')
    ->name('staff-id-cards.staff.view');

Route::get('/classes/{class}/attendance-report/{year}/{month}/download', ClassAttendanceReportPdfController::class)
    ->middleware('auth')
    ->whereNumber(['year', 'month'])
    ->name('attendance-report.class.download');

Route::get('/attendance-report/employees/{year}/{month}/download', EmployeeAttendanceReportPdfController::class)
    ->middleware('auth')
    ->whereNumber(['year', 'month'])
    ->name('attendance-report.employees.download');

Route::get('/attendance-report/teachers/{year}/{month}/download', TeacherAttendanceSheetPdfController::class)
    ->middleware('auth')
    ->whereNumber(['year', 'month'])
    ->name('attendance-report.teachers.download');

Route::get('/exams/{exam}/schedule/download', ExamSchedulePdfController::class)
    ->middleware('auth')
    ->name('exams.schedule.download');

Route::get('/exams/{exam}/tabulation-sheet/download', ExamTabulationSheetPdfController::class)
    ->middleware('auth')
    ->name('exams.tabulation-sheet.download');

Route::get('/exams/{exam}/result-report/download', ExamResultReportPdfController::class)
    ->middleware('auth')
    ->name('exams.result-report.download');

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
