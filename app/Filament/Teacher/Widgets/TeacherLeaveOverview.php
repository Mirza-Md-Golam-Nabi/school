<?php

namespace App\Filament\Teacher\Widgets;

use App\Enums\LeaveApplicationStatus;
use App\Filament\Teacher\Pages\MyLeave;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\TeacherProfile;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class TeacherLeaveOverview extends Widget
{
    protected string $view = 'filament.teacher.widgets.leave-overview';

    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected static bool $isLazy = false;

    public function getViewData(): array
    {
        $profile = Auth::user()?->teacherProfile;
        $url = MyLeave::getUrl(panel: 'teacher');

        if (! $profile || ! $profile->gender) {
            return ['totalAllowed' => 0, 'totalTaken' => 0, 'totalRemaining' => 0, 'url' => $url];
        }

        $totalAllowed = (int) LeaveType::query()
            ->active()
            ->availableFor($profile)
            ->sum('allowed_days_per_year');

        $totalTaken = (int) LeaveApplication::query()
            ->where('applicant_type', TeacherProfile::class)
            ->where('applicant_id', $profile->id)
            ->where('status', LeaveApplicationStatus::Approved)
            ->whereYear('from_date', now()->year)
            ->sum('total_days');

        return [
            'totalAllowed' => $totalAllowed,
            'totalTaken' => $totalTaken,
            'totalRemaining' => max(0, $totalAllowed - $totalTaken),
            'url' => $url,
        ];
    }
}
