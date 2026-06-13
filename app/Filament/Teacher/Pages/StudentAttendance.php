<?php

namespace App\Filament\Teacher\Pages;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Classes;
use App\Models\StudentProfile;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class StudentAttendance extends Page
{
    protected string $view = 'filament.teacher.pages.student-attendance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?string $navigationLabel = 'Student Attendance';

    protected static ?int $navigationSort = 1;

    public function getViewData(): array
    {
        $todayCounts = Attendance::query()
            ->where('attendable_type', StudentProfile::class)
            ->where('date', today())
            ->whereNull('subject_id')
            ->selectRaw('class_id, status, count(*) as total')
            ->groupBy('class_id', 'status')
            ->get()
            ->groupBy('class_id');

        $classes = Classes::where('is_active', true)
            ->withCount([
                'studentProfiles' => fn ($q) => $q->whereNull('deleted_at'),
            ])
            ->orderBy('order')
            ->get()
            ->each(function (Classes $class) use ($todayCounts) {
                $counts = $todayCounts->get($class->id, collect());
                $class->present_today = $counts->where('status', AttendanceStatus::Present->value)->sum('total');
                $class->absent_today = $counts->where('status', AttendanceStatus::Absent->value)->sum('total');
                $class->not_marked = max(0, $class->student_profiles_count - $class->present_today - $class->absent_today);
                $class->is_marked = ($class->present_today + $class->absent_today) > 0;
            });

        $totalStudents = $classes->sum('student_profiles_count');
        $presentToday = $classes->sum('present_today');
        $absentToday = $classes->sum('absent_today');
        $notMarkedToday = $totalStudents - $presentToday - $absentToday;

        return compact('classes', 'totalStudents', 'presentToday', 'absentToday', 'notMarkedToday');
    }
}
