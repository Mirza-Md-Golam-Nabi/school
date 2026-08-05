<?php

namespace App\Filament\Teacher\Pages;

use App\Enums\Permissions\AttendancePermission;
use App\Filament\Concerns\HasAttendancePagePermission;
use App\Filament\Concerns\ManagesClassAttendance;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class MarkStudentAttendance extends Page
{
    use HasAttendancePagePermission, ManagesClassAttendance;

    protected string $view = 'filament.teacher.pages.mark-student-attendance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static bool $shouldRegisterNavigation = false;

    protected static function attendancePermission(): AttendancePermission
    {
        return AttendancePermission::MARK_ATTENDANCE;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Mark Attendance — '.($this->resolveClass()?->name ?? 'Class');
    }

    public function getBreadcrumbs(): array
    {
        return [
            route('filament.teacher.pages.student-attendance') => 'Student Attendance',
            '' => $this->resolveClass()?->name ?? 'Class',
        ];
    }
}
