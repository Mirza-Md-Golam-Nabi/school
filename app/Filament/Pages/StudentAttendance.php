<?php

namespace App\Filament\Pages;

use App\Models\Classes;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class StudentAttendance extends Page
{
    protected string $view = 'filament.pages.student-attendance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?string $navigationLabel = 'Student Attendance';

    protected static ?int $navigationSort = 1;

    public function getViewData(): array
    {
        $classes = Classes::where('is_active', true)
            ->withCount([
                'studentProfiles' => fn ($q) => $q->whereNull('deleted_at'),
            ])
            ->orderBy('order')
            ->get();

        return compact('classes');
    }
}
