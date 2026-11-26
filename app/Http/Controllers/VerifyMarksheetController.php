<?php

namespace App\Http\Controllers;

use App\Actions\BuildMarksheetVerificationAction;
use App\Models\Marksheet;
use App\Models\SchoolSetting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * The public page a marksheet's QR code (or its typed-in code) opens: the
 * student's current official result, subject by subject, so whoever holds the
 * paper can spot a changed mark. Contact details are never shown, and nothing
 * of the result before the exam's result is published.
 */
class VerifyMarksheetController extends Controller
{
    public function __invoke(string $code, BuildMarksheetVerificationAction $buildMarksheetVerification): Response
    {
        $marksheet = Marksheet::findByVerificationCode($code)
            ?->load(['student.user', 'student.class', 'student.section', 'student.group', 'exam.examType', 'exam.class']);

        $logo = SchoolSetting::get('school_logo');

        return response()->view('verify-marksheet', [
            'marksheet' => $marksheet,
            'result' => $marksheet ? $buildMarksheetVerification->handle($marksheet) : null,
            'schoolName' => (string) config('app.school_name'),
            'schoolLogoUrl' => filled($logo) ? Storage::disk('public')->url($logo) : null,
        ], $marksheet ? 200 : 404);
    }
}
