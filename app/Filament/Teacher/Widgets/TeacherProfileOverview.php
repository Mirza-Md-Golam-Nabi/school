<?php

namespace App\Filament\Teacher\Widgets;

use App\Filament\Teacher\Pages\MyProfile;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Facades\Auth;

class TeacherProfileOverview extends StatsOverviewWidget
{
    protected string $view = 'filament.teacher.widgets.profile-overview';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected static bool $isLazy = false;

    public function getViewData(): array
    {
        $user = Auth::user();
        $profile = $user->teacherProfile;

        return [
            'name' => $user->name,
            'designation' => $profile?->designation ?? '—',
            'department' => $profile?->department,
            'status' => $profile?->status,
            'url' => MyProfile::getUrl(panel: 'teacher'),
        ];
    }
}
