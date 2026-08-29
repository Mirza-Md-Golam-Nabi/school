<?php

namespace App\Filament\Student\Widgets;

use App\Filament\Student\Resources\ExamResults\ExamResultResource;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class StudentLatestResultWidget extends Widget
{
    protected string $view = 'filament.student.widgets.latest-result';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
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

        $ranking = static::latestRanking($profile)
            ->with(['exam.examType', 'class'])
            ->get()
            ->sortByDesc(fn (StudentMeritRanking $ranking): string => (string) $ranking->exam?->start_date)
            ->first();

        // No published result for the current class/session yet (e.g. right after a
        // promotion) — show the widget with zeroed-out values instead of hiding it.
        if (! $ranking) {
            return [
                'examLabel' => __('Exam'),
                'gpa' => 0,
                'classRank' => null,
                'totalStudents' => StudentProfile::where('current_class_id', $profile->current_class_id)
                    ->active()
                    ->count(),
                'url' => ExamResultResource::getUrl(panel: 'student'),
            ];
        }

        $totalStudents = StudentProfile::where('current_class_id', $ranking->class_id)
            ->active()
            ->count();

        return [
            'examLabel' => $ranking->exam?->examType?->name ?? __('Exam'),
            'gpa' => $ranking->gpa,
            'classRank' => $ranking->class_rank,
            'totalStudents' => $totalStudents,
            'url' => ExamResultResource::getUrl('view', ['record' => $ranking->exam_id], panel: 'student'),
        ];
    }

    /**
     * Scoped to the student's current class + session_year, so a promotion
     * (class/session_year change) resets this widget rather than continuing
     * to show a result from the class they've already left.
     */
    private static function latestRanking(StudentProfile $profile): Builder
    {
        return StudentMeritRanking::where('student_id', $profile->id)
            ->where('class_id', $profile->current_class_id)
            ->whereHas('exam', fn ($query) => $query
                ->where('session_year', $profile->session_year)
                ->where('is_published', true));
    }
}
