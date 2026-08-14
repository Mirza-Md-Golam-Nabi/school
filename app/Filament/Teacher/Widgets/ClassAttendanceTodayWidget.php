<?php

namespace App\Filament\Teacher\Widgets;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\StudentProfile;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class ClassAttendanceTodayWidget extends Widget
{
    protected string $view = 'filament.teacher.widgets.class-attendance-today';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = [
        'default' => 1,
    ];

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return Auth::user()?->teacherProfile
            ?->classesAsClassTeacher()
            ->exists() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $classes = Auth::user()->teacherProfile
            ->classesAsClassTeacher()
            ->withCount([
                'studentProfiles' => fn ($query) => $query->whereNull('deleted_at'),
            ])
            ->orderBy('order')
            ->get();

        $attendanceByClass = Attendance::query()
            ->where('attendable_type', StudentProfile::class)
            ->where('date', today()->toDateString())
            ->whereNull('subject_id')
            ->whereIn('class_id', $classes->pluck('id'))
            ->selectRaw('class_id, status, count(*) as total')
            ->groupBy('class_id', 'status')
            ->get()
            ->groupBy('class_id');

        $classes->each(function ($class) use ($attendanceByClass): void {
            $counts = $attendanceByClass->get($class->id, collect());
            $class->present_today = (int) $counts->where('status', AttendanceStatus::Present->value)->sum('total');
            $class->absent_today = (int) $counts->where('status', AttendanceStatus::Absent->value)->sum('total');
            $class->is_marked = ($class->present_today + $class->absent_today) > 0;
        });

        return [
            'classes' => $classes,
            'markUrl' => route('filament.teacher.pages.mark-student-attendance'),
        ];
    }
}
