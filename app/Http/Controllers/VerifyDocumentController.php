<?php

namespace App\Http\Controllers;

use App\Models\Marksheet;
use App\Models\SchoolSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * The short link printed under a marksheet's QR code: whoever can't scan it
 * types the printed code here and lands on the same verification page.
 */
class VerifyDocumentController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $code = trim((string) $request->query('code', ''));

        if ($code !== '' && $marksheet = Marksheet::findByVerificationCode($code)) {
            return redirect()->route('marksheets.verify', ['code' => $marksheet->verification_code]);
        }

        $logo = SchoolSetting::get('school_logo');

        return response()->view('verify-document', [
            'code' => $code,
            'notFound' => $code !== '',
            'schoolName' => (string) config('app.school_name'),
            'schoolLogoUrl' => filled($logo) ? Storage::disk('public')->url($logo) : null,
        ], $code !== '' ? 404 : 200);
    }
}
