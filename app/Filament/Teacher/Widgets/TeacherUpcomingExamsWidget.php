<?php

namespace App\Filament\Teacher\Widgets;

use App\Filament\Teacher\Concerns\ScopesToTaughtClasses;
use App\Filament\Teacher\Resources\Exams\ExamResource;
use App\Models\Exam;
use Filament\Widgets\Widget;

class TeacherUpcomingExamsWidget extends Widget
{
    use ScopesToTaughtClasses;

    protected string $view = 'filament.teacher.widgets.upcoming-exams';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = [
        'default' => 'full',
    ];

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return count(static::currentTeacherTaughtClassIds()) > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $exams = Exam::query()
            ->whereIn('class_id', static::currentTeacherTaughtClassIds())
            ->where('session_year', now()->year)
            ->whereDate('start_date', '>=', today()->toDateString())
            ->with(['examType', 'class'])
            ->orderBy('start_date')
            ->limit(6)
            ->get()
            ->map(fn (Exam $exam): array => [
                'label' => ($exam->examType?->name ?? __('Exam')).' — '.($exam->class?->name ?? ''),
                'date' => $exam->start_date?->format('d M, Y'),
                'isPublished' => $exam->is_published,
                'url' => ExamResource::getUrl('view', ['record' => $exam->id], panel: 'teacher'),
            ]);

        return ['exams' => $exams];
    }
}
