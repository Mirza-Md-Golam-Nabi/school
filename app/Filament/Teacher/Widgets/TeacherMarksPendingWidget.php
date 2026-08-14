<?php

namespace App\Filament\Teacher\Widgets;

use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\TeacherSubject;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class TeacherMarksPendingWidget extends Widget
{
    protected string $view = 'filament.teacher.widgets.marks-pending';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = [
        'default' => 1,
    ];

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return TeacherSubject::where('teacher_id', Auth::user()?->teacherProfile?->id)
            ->where('session_year', now()->year)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $session = now()->year;

        $assignments = TeacherSubject::where('teacher_id', Auth::user()->teacherProfile->id)
            ->where('session_year', $session)
            ->get(['class_id', 'subject_id']);

        $exams = Exam::whereIn('class_id', $assignments->pluck('class_id')->unique())
            ->where('session_year', $session)
            ->with(['examType', 'class'])
            ->get();

        $examIds = $exams->pluck('id');

        $configKeys = ExamSubjectConfig::whereIn('exam_id', $examIds)
            ->get(['exam_id', 'subject_id'])
            ->map(fn (ExamSubjectConfig $config): string => $config->exam_id.'-'.$config->subject_id)
            ->flip();

        $enteredKeys = StudentResult::whereIn('exam_id', $examIds)
            ->select('exam_id', 'subject_id')
            ->distinct()
            ->get()
            ->map(fn (StudentResult $result): string => $result->exam_id.'-'.$result->subject_id)
            ->flip();

        $subjectNames = Subject::whereIn('id', $assignments->pluck('subject_id')->unique())
            ->pluck('name', 'id');

        $pending = [];

        foreach ($exams as $exam) {
            $subjectIds = $assignments->where('class_id', $exam->class_id)->pluck('subject_id')->unique();

            foreach ($subjectIds as $subjectId) {
                $key = $exam->id.'-'.$subjectId;

                if (! isset($configKeys[$key]) || isset($enteredKeys[$key])) {
                    continue;
                }

                $pending[] = [
                    'label' => ($exam->examType?->name ?? 'Exam').' — '.($subjectNames[$subjectId] ?? 'Subject'),
                    'sublabel' => $exam->class?->name,
                    'url' => route('filament.teacher.pages.enter-student-marks').'?'.http_build_query([
                        'examId' => $exam->id,
                        'subjectId' => $subjectId,
                        'classId' => $exam->class_id,
                    ]),
                ];
            }
        }

        return ['pending' => $pending];
    }
}
