<?php

namespace App\Actions;

use App\Enums\ClassLevel;
use App\Models\Exam;
use App\Models\GradeScale;
use App\Models\Group;
use App\Models\StudentMeritRanking;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Support\ClassGroupSubjectDefinitions;
use Illuminate\Support\Collection;

class BuildExamTabulationSheetDetail
{
    /**
     * Build the print-ready data for an exam's tabulation sheet from the
     * already-calculated StudentMeritRanking rows: one row per student, one
     * column per subject's total mark. The column set — and whether the
     * sheet is split into group sections — depends on the exam's class:
     * Primary and Class 6-8 get one flat table, Class 9-10 (grouped) gets
     * one table per group, since each group takes different subjects.
     *
     * @return array<string, mixed>
     */
    public function handle(Exam $exam): array
    {
        $exam->loadMissing(['examType', 'class']);

        $hasSections = $exam->class?->sections()->exists() ?? false;

        $rankings = $exam->meritRankings()
            ->with(['student.user', 'student.group', 'section'])
            ->orderBy('class_rank')
            ->get();

        if ($rankings->isEmpty()) {
            return [
                'exam' => $exam,
                'hasSections' => $hasSections,
                'view' => null,
                'orientation' => 'P',
                'isEmpty' => true,
            ];
        }

        $subjectConfigs = $exam->subjectConfigs()->with('subject')->get()->keyBy('subject_id');

        $resultsByStudent = StudentResult::where('exam_id', $exam->id)
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $results): Collection => $results->keyBy('subject_id'));

        $gradeScales = GradeScale::cached();

        $class = $exam->class;

        if ($class?->has_group) {
            return [
                'exam' => $exam,
                'hasSections' => $hasSections,
                'view' => 'documents.tabulation-sheet-secondary-grouped',
                'orientation' => 'L',
                'isEmpty' => false,
                'blocks' => $this->buildGroupBlocks($exam, $rankings, $resultsByStudent, $gradeScales, $hasSections),
            ];
        }

        $subjects = $subjectConfigs->pluck('subject')->filter()->sortBy('name')->values();

        return [
            'exam' => $exam,
            'hasSections' => $hasSections,
            'view' => $class?->level === ClassLevel::Primary
                ? 'documents.tabulation-sheet-primary'
                : 'documents.tabulation-sheet-secondary',
            'orientation' => $class?->level === ClassLevel::Primary ? 'P' : 'L',
            'isEmpty' => false,
            'subjects' => $subjects,
            'rows' => $rankings->map(
                fn (StudentMeritRanking $ranking): array => $this->buildRow($ranking, $subjects, $resultsByStudent, $gradeScales, $hasSections)
            )->values(),
        ];
    }

    /**
     * @param  Collection<int, StudentMeritRanking>  $rankings
     * @param  Collection<int, Collection<int, StudentResult>>  $resultsByStudent
     * @param  Collection<int, GradeScale>  $gradeScales
     * @return array<int, array<string, mixed>>
     */
    private function buildGroupBlocks(
        Exam $exam,
        Collection $rankings,
        Collection $resultsByStudent,
        Collection $gradeScales,
        bool $hasSections
    ): array {
        $groups = $exam->class->groups
            ->sortBy(fn (Group $group): int => array_search($group->name, ClassGroupSubjectDefinitions::SECONDARY_GROUPS, true))
            ->values();

        $blocks = [];

        foreach ($groups as $group) {
            $groupRankings = $rankings
                ->filter(fn (StudentMeritRanking $ranking): bool => $ranking->student?->current_group_id === $group->id)
                ->values();

            if ($groupRankings->isEmpty()) {
                continue;
            }

            $groupSubjects = $exam->class->subjectsForGroup($group->id)
                ->pluck('subject')
                ->filter()
                ->unique('id')
                ->filter(fn (Subject $subject): bool => $groupRankings->contains(
                    fn (StudentMeritRanking $ranking): bool => $resultsByStudent->get($ranking->student_id)?->has($subject->id) ?? false
                ))
                ->sortBy('name')
                ->values();

            $blocks[] = [
                'group' => $group,
                'subjects' => $groupSubjects,
                'rows' => $groupRankings->map(
                    fn (StudentMeritRanking $ranking): array => $this->buildRow($ranking, $groupSubjects, $resultsByStudent, $gradeScales, $hasSections)
                )->values(),
            ];
        }

        return $blocks;
    }

    /**
     * @param  Collection<int, Subject>  $subjects
     * @param  Collection<int, Collection<int, StudentResult>>  $resultsByStudent
     * @param  Collection<int, GradeScale>  $gradeScales
     * @return array<string, mixed>
     */
    private function buildRow(
        StudentMeritRanking $ranking,
        Collection $subjects,
        Collection $resultsByStudent,
        Collection $gradeScales,
        bool $hasSections
    ): array {
        $studentResults = $resultsByStudent->get($ranking->student_id) ?? collect();

        $marks = $subjects->mapWithKeys(function (Subject $subject) use ($studentResults): array {
            $result = $studentResults->get($subject->id);

            return [$subject->id => [
                'value' => ($result && ! $result->is_absent) ? $result->effective_marks : null,
                'is_absent' => (bool) $result?->is_absent,
            ]];
        });

        return [
            'roll_no' => $ranking->student?->roll_no,
            'name' => $ranking->student?->user?->name ?? '—',
            'section_name' => $hasSections ? $ranking->section?->name : null,
            'marks' => $marks,
            'total_marks' => $ranking->total_marks,
            'gpa' => $ranking->gpa,
            'grade_label' => GradeScale::fromGpa((float) $ranking->gpa, $gradeScales)?->letter_grade,
            'class_rank' => $ranking->class_rank,
            'section_rank' => $ranking->section_rank,
        ];
    }
}
