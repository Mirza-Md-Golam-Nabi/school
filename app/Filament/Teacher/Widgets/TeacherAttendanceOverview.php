<?php

namespace App\Filament\Teacher\Widgets;

use App\Filament\Teacher\Pages\MyAttendance;
use App\Models\Attendance;
use App\Models\TeacherProfile;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class TeacherAttendanceOverview extends Widget
{
    protected string $view = 'filament.teacher.widgets.attendance-overview';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected static bool $isLazy = false;

    public function getViewData(): array
    {
        $profile = Auth::user()->teacherProfile;
        $attendance = null;

        if ($profile) {
            $baseQuery = fn () => Attendance::where('attendable_type', TeacherProfile::class)
                ->where('attendable_id', $profile->id)
                ->whereNull('class_id')
                ->whereNull('subject_id');

            $today = now()->toDateString();

            $attendance = $baseQuery()->where('date', $today)->first()
                ?? $baseQuery()->where('date', '<', $today)->orderByDesc('date')->first();
        }

        $url = MyAttendance::getUrl(panel: 'teacher');

        if (! $attendance) {
            return [
                'isToday' => false,
                'date' => null,
                'status' => null,
                'time' => null,
                'url' => $url,
            ];
        }

        return [
            'isToday' => $attendance->date->toDateString() === now()->toDateString(),
            'date' => $attendance->date->format('d M, Y'),
            'status' => $attendance->status,
            'time' => $attendance->entry_time ? Carbon::parse($attendance->entry_time)->format('h:i A') : null,
            'url' => $url,
        ];
    }
}
