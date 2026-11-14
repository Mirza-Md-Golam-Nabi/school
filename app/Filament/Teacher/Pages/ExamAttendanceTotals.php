<?php

namespace App\Filament\Teacher\Pages;

use App\Enums\Permissions\AttendancePermission;
use App\Filament\Concerns\HasAttendancePagePermission;
use App\Filament\Concerns\ManagesExamAttendanceTotals;
use App\Models\Classes;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

class ExamAttendanceTotals extends Page
{
    use HasAttendancePagePermission {
        canAccess as hasAttendancePermission;
    }
    use ManagesExamAttendanceTotals;

    protected string $view = 'filament.shared.exam-attendance-totals';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?string $navigationLabel = 'Attendance Totals';

    protected static ?string $title = 'Attendance Totals';

    protected static ?int $navigationSort = 3;

    protected static function attendancePermission(): AttendancePermission
    {
        return AttendancePermission::MARK_ATTENDANCE;
    }

    /**
     * Only class teachers see this page — and only for their own classes.
     */
    public static function canAccess(): bool
    {
        return static::hasAttendancePermission()
            && (auth()->user()?->teacherProfile?->classesAsClassTeacher()->exists() ?? false);
    }

    protected function canManageClass(int $classId): bool
    {
        $teacherId = auth()->user()?->teacherProfile?->id;

        return $teacherId !== null
            && Classes::whereKey($classId)->where('class_teacher_id', $teacherId)->exists();
    }

    public function getClasses(): Collection
    {
        $teacherProfile = auth()->user()?->teacherProfile;

        if (! $teacherProfile) {
            return collect();
        }

        return $teacherProfile->classesAsClassTeacher()->orderBy('order')->get(['id', 'name']);
    }
}
