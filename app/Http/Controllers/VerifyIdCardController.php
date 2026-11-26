<?php

namespace App\Http\Controllers;

use App\Models\SchoolSetting;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * The public page an ID card's QR code opens: it shows whom the card was
 * issued to (photo, name, ID, current class or post) and — most importantly —
 * whether that person is still at the school, so a card kept after leaving
 * is caught. A reissued (lost) card or a forged one says plainly that no such
 * card exists. Contact details are never shown.
 */
class VerifyIdCardController extends Controller
{
    public function __invoke(string $token): Response
    {
        $profile = StudentProfile::findByIdCardToken($token)?->load(['user', 'class', 'section', 'group'])
            ?? TeacherProfile::findByIdCardToken($token)?->load('user')
            ?? StaffProfile::findByIdCardToken($token)?->load('user');

        $logo = SchoolSetting::get('school_logo');

        return response()->view('verify-id-card', [
            'profile' => $profile,
            'schoolName' => (string) config('app.school_name'),
            'schoolLogoUrl' => filled($logo) ? Storage::disk('public')->url($logo) : null,
        ], $profile ? 200 : 404);
    }
}
