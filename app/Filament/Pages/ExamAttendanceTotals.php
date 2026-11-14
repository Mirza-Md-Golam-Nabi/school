<?php

namespace App\Filament\Pages;

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
    use HasAttendancePagePermission, ManagesExamAttendanceTotals;

    protected string $view = 'filament.shared.exam-attendance-totals';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?string $navigationLabel = 'Attendance Totals';

    protected static ?string $title = 'Attendance Totals';

    protected static ?int $navigationSort = 5;

    protected static function attendancePermission(): AttendancePermission
    {
        return AttendancePermission::MARK_ATTENDANCE;
    }

    /**
     * An admin may write totals for any class.
     */
    protected function canManageClass(int $classId): bool
    {
        return Classes::whereKey($classId)->exists();
    }

    public function getClasses(): Collection
    {
        return Classes::active()->orderBy('order')->get(['id', 'name']);
    }
}
