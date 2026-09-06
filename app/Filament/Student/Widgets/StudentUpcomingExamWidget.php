<?php

namespace App\Filament\Student\Widgets;

use App\Models\Exam;
use App\Models\ExamSchedule;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class StudentUpcomingExamWidget extends Widget
{
    protected string $view = 'filament.student.widgets.upcoming-exam';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = [
        'default' => 1,
    ];

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return filled(Auth::user()?->studentProfile?->current_class_id);
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $profile = Auth::user()->studentProfile;

        $exams = Exam::query()
            ->where('class_id', $profile->current_class_id)
            ->where('session_year', now()->year)
            ->whereDate('start_date', '>=', today()->toDateString())
            ->with(['examType', 'schedules.subject'])
            ->orderBy('start_date')
            ->limit(5)
            ->get()
            ->map(fn (Exam $exam): array => [
                'label' => $exam->examType?->name ?? __('Exam'),
                'startDate' => $exam->start_date?->format('d M, Y'),
                'endDate' => $exam->end_date?->format('d M, Y'),
                'schedules' => $exam->schedules
                    ->sortBy('exam_date')
                    ->map(fn (ExamSchedule $schedule): array => [
                        'subject' => $schedule->subject?->name ?? __('Subject'),
                        'date' => $schedule->exam_date?->format('d M, Y (D)'),
                    ])
                    ->values(),
            ]);

        return ['exams' => $exams];
    }
}
