<?php

namespace App\Filament\Student\Widgets;

use App\Models\Attendance;
use App\Models\StudentProfile;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class StudentTodayAttendance extends Widget
{
    protected string $view = 'filament.student.widgets.today-attendance';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected static bool $isLazy = false;

    public function getViewData(): array
    {
        $profile = Auth::user()->studentProfile;
        $attendance = null;

        if ($profile) {
            $baseQuery = fn () => Attendance::where('attendable_type', StudentProfile::class)
                ->where('attendable_id', $profile->id)
                ->whereNull('subject_id');

            $today = now()->toDateString();

            $attendance = $baseQuery()->where('date', $today)->first()
                ?? $baseQuery()->where('date', '<', $today)->orderByDesc('date')->first();
        }

        if (! $attendance) {
            return [
                'isToday' => false,
                'date' => null,
                'status' => null,
                'time' => null,
            ];
        }

        return [
            'isToday' => $attendance->date->toDateString() === now()->toDateString(),
            'date' => $attendance->date->format('d M, Y'),
            'status' => $attendance->status,
            'time' => $attendance->entry_time ? Carbon::parse($attendance->entry_time)->format('h:i A') : null,
        ];
    }
}
