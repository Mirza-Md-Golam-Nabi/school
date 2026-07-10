<?php

namespace App\Filament\Teacher\Widgets;

use App\Filament\Teacher\Pages\MySubjects;
use App\Models\TeacherSubject;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Facades\Auth;

class TeacherSubjectsOverview extends StatsOverviewWidget
{
    protected string $view = 'filament.teacher.widgets.subjects-overview';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected static bool $isLazy = false;

    public function getViewData(): array
    {
        $url = MySubjects::getUrl(panel: 'teacher');
        $teacherId = Auth::user()?->teacherProfile?->id;

        if (! $teacherId) {
            return [
                'classCount' => 0,
                'subjectCount' => 0,
                'url' => $url,
            ];
        }

        $assignments = TeacherSubject::where('teacher_id', $teacherId)
            ->where('session_year', (int) now()->format('Y'))
            ->get(['class_id', 'subject_id']);

        return [
            'classCount' => $assignments->pluck('class_id')->unique()->count(),
            // Each row is a distinct class-subject teaching assignment, so the
            // same subject taught across multiple classes counts separately.
            'subjectCount' => $assignments->count(),
            'url' => $url,
        ];
    }
}
