<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ManagesClassAttendance;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class MarkStudentAttendance extends Page
{
    use ManagesClassAttendance;

    protected string $view = 'filament.pages.mark-student-attendance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string|Htmlable
    {
        return 'Mark Attendance — '.($this->resolveClass()?->name ?? 'Class');
    }

    public function getBreadcrumbs(): array
    {
        return [
            route('filament.admin.pages.student-attendance') => 'Student Attendance',
            '' => $this->resolveClass()?->name ?? 'Class',
        ];
    }
}
