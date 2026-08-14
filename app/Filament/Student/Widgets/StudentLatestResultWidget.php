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
        $profile = Auth::user()?->studentProfile;

        if (! $profile) {
            return false;
        }

        return static::latestRanking($profile)->exists();
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

        $totalStudents = StudentProfile::where('current_class_id', $ranking->class_id)
            ->active()
            ->count();

        return [
            'examLabel' => $ranking->exam?->examType?->name ?? 'Exam',
            'gpa' => $ranking->gpa,
            'classRank' => $ranking->class_rank,
            'totalStudents' => $totalStudents,
            'url' => ExamResultResource::getUrl('view', ['record' => $ranking->exam_id], panel: 'student'),
        ];
    }

    private static function latestRanking(StudentProfile $profile): Builder
    {
        return StudentMeritRanking::where('student_id', $profile->id)
            ->whereHas('exam', fn ($query) => $query->where('is_published', true));
    }
}
