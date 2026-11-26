<?php

namespace App\Http\Controllers;

use App\Models\AdmitCard;
use App\Models\SchoolSetting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * The public page an admit card's QR code opens: it shows whom the card was
 * issued to (photo, name, class, roll, exam) so an invigilator can match it
 * against the student in the exam hall, and says plainly when no such card
 * exists (a forged or deleted card). Contact details are never shown.
 */
class VerifyAdmitCardController extends Controller
{
    public function __invoke(string $token): Response
    {
        $admitCard = AdmitCard::query()
            ->where('verification_token', $token)
            ->with(['student.user', 'student.class', 'student.section', 'student.group', 'exam.examType'])
            ->first();

        $logo = SchoolSetting::get('school_logo');

        return response()->view('verify-admit-card', [
            'admitCard' => $admitCard,
            'schoolName' => (string) config('app.school_name'),
            'schoolLogoUrl' => filled($logo) ? Storage::disk('public')->url($logo) : null,
        ], $admitCard ? 200 : 404);
    }
}
