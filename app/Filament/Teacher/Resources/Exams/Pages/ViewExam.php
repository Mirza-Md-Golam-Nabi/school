<?php

namespace App\Filament\Teacher\Resources\Exams\Pages;

use App\Filament\Teacher\Pages\ExamAttendanceTotals;
use App\Filament\Teacher\Resources\Exams\ExamResource;
use App\Models\Exam;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewExam extends ViewRecord
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('attendanceTotals')
                ->label('Attendance Totals')
                ->icon(Heroicon::OutlinedCalculator)
                ->color('gray')
                ->visible(fn (Exam $record): bool => ExamAttendanceTotals::canAccess()
                    && $record->class?->class_teacher_id === auth()->user()?->teacherProfile?->id)
                ->url(fn (Exam $record): string => ExamAttendanceTotals::getUrl(['examId' => $record->id])),
        ];
    }
}
