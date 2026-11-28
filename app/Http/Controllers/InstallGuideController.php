<?php

namespace App\Http\Controllers;

use App\Models\SchoolSetting;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Public, step-by-step guide for installing the admin, student or teacher panel
 * as an app. It lives inside the panel's path so the browser treats it as
 * part of that app and can offer the native install prompt here too.
 */
class InstallGuideController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const APP_LABELS = [
        'admin' => 'অ্যাডমিন',
        'student' => 'শিক্ষার্থী',
        'teacher' => 'শিক্ষক',
    ];

    public function __invoke(string $panel): View
    {
        $logo = SchoolSetting::get('school_logo');
        $filamentPanel = Filament::getPanel($panel);

        return view('install-guide', [
            'panelId' => $panel,
            'appLabel' => self::APP_LABELS[$panel],
            'guideUrl' => route('install-guide', ['panel' => $panel]),
            'loginUrl' => $filamentPanel->getLoginUrl() ?? $filamentPanel->getUrl(),
            'schoolName' => (string) config('app.school_name'),
            'schoolLogoUrl' => filled($logo) ? Storage::disk('public')->url($logo) : null,
        ]);
    }
}
