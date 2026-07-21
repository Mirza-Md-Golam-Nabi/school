<?php

namespace App\Filament\Student\Resources\ExamResults\Pages;

use App\Enums\Grade;
use App\Filament\Student\Resources\ExamResults\ExamResultResource;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\StudentMeritRanking;
use App\Models\StudentResult;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class ViewExamResult extends Page
{
    protected static string $resource = ExamResultResource::class;

    protected string $view = 'filament.student.pages.view-exam-result';

    public Exam $record;

    public function mount(Exam $record): void
    {
        $this->record = $record->load(['examType', 'class']);
    }

    public function getBreadcrumbs(): array
    {
        return [
            ExamResultResource::getUrl() => 'Exam Results',
            '#' => $this->record->examType?->name.' — '.$this->record->session_year,
        ];
    }

    public function getTitle(): string
    {
        return ($this->record->examType?->name ?? 'Exam').' — '.$this->record->session_year;
    }

    public function getRankings(): Collection
    {
        return StudentMeritRanking::where('exam_id', $this->record->id)
            ->with(['student.user', 'section'])
            ->orderBy('class_rank')
            ->get();
    }

    public function getMyStudentId(): ?int
    {
        $student = ExamResultResource::getStudentProfile();

        return $student?->id;
    }

    public function getMyMarksDetail(int $rankingId): View
    {
        $ranking = StudentMeritRanking::findOrFail($rankingId);

        abort_unless((int) $ranking->student_id === (int) $this->getMyStudentId(), 403);

        $myResults = StudentResult::where('exam_id', $ranking->exam_id)
            ->where('student_id', $ranking->student_id)
            ->with('subject')
            ->get()
            ->keyBy('subject_id');

        $subjectConfigs = ExamSubjectConfig::where('exam_id', $ranking->exam_id)
            ->get()
            ->keyBy('subject_id');

        $allResults = StudentResult::where('exam_id', $ranking->exam_id)
            ->with('student.user')
            ->get()
            ->groupBy('subject_id');

        $subjectBestMap = [];
        foreach ($allResults as $subjectId => $results) {
            $bestMarks = $results->max('total_marks');
            $subjectBestMap[$subjectId] = [
                'best_marks' => $bestMarks,
                'best_students' => $results
                    ->filter(fn ($r) => (float) $r->total_marks === (float) $bestMarks && ! $r->is_absent)
                    ->map(fn ($r) => $r->student?->user?->name)
                    ->filter()
                    ->implode(', '),
            ];
        }

        $rows = $myResults->map(function (StudentResult $result) use ($subjectConfigs, $subjectBestMap): array {
            $subjectId = $result->subject_id;
            $config = $subjectConfigs->get($subjectId);
            $fullMarks = $config?->total_marks ?: 100;
            $percentage = (! $result->is_absent && $fullMarks > 0)
                ? ($result->total_marks / $fullMarks) * 100
                : 0.0;
            $grade = (! $result->is_absent && $result->total_marks > 0)
                ? Grade::fromMarks($percentage)
                : null;
            $best = $subjectBestMap[$subjectId] ?? null;

            return [
                'subject_name' => $result->subject?->name ?? '—',
                'is_absent' => $result->is_absent,
                'total_marks' => $result->total_marks,
                'grade_label' => $grade?->getLabel(),
                'grade_color' => $grade?->getColor(),
                'is_top_scorer' => $best !== null
                    && ! $result->is_absent
                    && (float) $result->total_marks === (float) $best['best_marks'],
                'best_marks' => $best['best_marks'] ?? null,
                'best_students' => $best['best_students'] ?? null,
            ];
        })->values();

        $overallGrade = Grade::fromGpa((float) $ranking->gpa);

        $summary = [
            'total_marks' => $ranking->total_marks,
            'class_rank' => $ranking->class_rank,
            'section_rank' => $ranking->section_rank,
            'gpa' => number_format((float) $ranking->gpa, 2),
            'overall_grade_label' => $overallGrade->getLabel(),
            'overall_grade_color' => $overallGrade->getColor(),
        ];

        return view('filament.shared.student-marks-detail', compact('rows', 'summary'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to Results')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(ExamResultResource::getUrl()),
        ];
    }
}
