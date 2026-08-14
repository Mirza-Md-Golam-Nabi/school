<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Exams\ExamResource;
use App\Models\Exam;
use Filament\Widgets\Widget;

class AcademicStatsWidget extends Widget
{
    protected string $view = 'filament.widgets.academic-stats';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = [
        'default' => 1,
        'md' => 1,
        'lg' => 1,
    ];

    protected static bool $isLazy = false;

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $sessionYear = now()->year;

        $examCounts = Exam::query()
            ->where('session_year', $sessionYear)
            ->selectRaw('is_published, count(*) as total')
            ->groupBy('is_published')
            ->pluck('total', 'is_published');

        $publishedExams = (int) ($examCounts[1] ?? 0);
        $unpublishedExams = (int) ($examCounts[0] ?? 0);

        return [
            'sessionYear' => $sessionYear,
            'totalExams' => $publishedExams + $unpublishedExams,
            'publishedExams' => $publishedExams,
            'unpublishedExams' => $unpublishedExams,
            'examsUrl' => ExamResource::getUrl('index'),
        ];
    }
}
