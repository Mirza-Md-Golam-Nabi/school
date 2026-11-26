<?php

namespace App\Actions;

use App\Enums\ClassLevel;
use App\Models\ClassGroupSubject;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamSubjectMerge;
use App\Models\GradeScale;
use App\Models\Group;
use App\Models\StudentMeritRanking;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Support\ClassGroupSubjectDefinitions;
use Illuminate\Support\Collection;

class BuildExamTabulationSheetDetail
{
    public function __construct(private readonly ResolveMergedSubjectOutcome $resolveMergedSubjectOutcome) {}

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
            ->get()
            ->sortBy(fn (StudentMeritRanking $ranking): int => (int) $ranking->student?->roll_no)
            ->values();

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

        $merges = $exam->subjectMerges()->get();
        $mergeContext = [
            'merges' => $merges,
            'subjectConfigs' => $subjectConfigs,
            'gradeScales' => $gradeScales,
            'subjectTypeRecords' => ClassGroupSubject::recordsBySubjectForClass($exam->class_id),
        ];

        $class = $exam->class;

        if ($class?->has_group) {
            return [
                'exam' => $exam,
                'hasSections' => $hasSections,
                'view' => 'documents.tabulation-sheet-secondary-grouped',
                'orientation' => 'L',
                'isEmpty' => false,
                'hasMerges' => $merges->isNotEmpty(),
                'blocks' => $this->buildGroupBlocks($exam, $rankings, $resultsByStudent, $gradeScales, $hasSections, $mergeContext),
            ];
        }

        $subjects = $this->inSubjectOrder($subjectConfigs->pluck('subject')->filter());

        return [
            'exam' => $exam,
            'hasSections' => $hasSections,
            'view' => $class?->level === ClassLevel::Primary
                ? 'documents.tabulation-sheet-primary'
                : 'documents.tabulation-sheet-secondary',
            'orientation' => $class?->level === ClassLevel::Primary ? 'P' : 'L',
            'isEmpty' => false,
            'subjects' => $subjects,
            'hasMerges' => $merges->isNotEmpty(),
            'columns' => $this->columnGroups($subjects, $merges, $subjectConfigs),
            'rows' => $rankings->map(
                fn (StudentMeritRanking $ranking): array => $this->buildRow($ranking, $subjects, $resultsByStudent, $gradeScales, $hasSections, $mergeContext)
            )->values(),
        ];
    }

    /**
     * @param  Collection<int, StudentMeritRanking>  $rankings
     * @param  Collection<int, Collection<int, StudentResult>>  $resultsByStudent
     * @param  Collection<int, GradeScale>  $gradeScales
     * @param  array{merges: Collection<int, ExamSubjectMerge>, subjectConfigs: Collection, gradeScales: Collection, subjectTypeRecords: Collection}  $mergeContext
     * @return array<int, array<string, mixed>>
     */
    private function buildGroupBlocks(
        Exam $exam,
        Collection $rankings,
        Collection $resultsByStudent,
        Collection $gradeScales,
        bool $hasSections,
        array $mergeContext
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

            // The exam's subjects this group takes — a paper (Biology 1st
            // Paper) by its main subject's syllabus record.
            $groupSubjects = $this->inSubjectOrder(
                $mergeContext['subjectConfigs']
                    ->pluck('subject')
                    ->filter()
                    ->filter(fn (Subject $subject): bool => ClassGroupSubject::isTakenByGroup($mergeContext['subjectTypeRecords']->get((int) $subject->id), (int) $group->id))
                    ->filter(fn (Subject $subject): bool => $groupRankings->contains(
                        fn (StudentMeritRanking $ranking): bool => $resultsByStudent->get($ranking->student_id)?->has($subject->id) ?? false
                    ))
            );

            $blocks[] = [
                'group' => $group,
                'subjects' => $groupSubjects,
                'columns' => $this->columnGroups(
                    $groupSubjects,
                    ExamSubjectMerge::applyingToGroup($mergeContext['merges'], $mergeContext['subjectTypeRecords'], (int) $group->id),
                    $mergeContext['subjectConfigs'],
                ),
                'rows' => $groupRankings->map(
                    fn (StudentMeritRanking $ranking): array => $this->buildRow($ranking, $groupSubjects, $resultsByStudent, $gradeScales, $hasSections, $mergeContext)
                )->values(),
            ];
        }

        return $blocks;
    }

    /**
     * @param  Collection<int, Subject>  $subjects
     * @param  Collection<int, Collection<int, StudentResult>>  $resultsByStudent
     * @param  Collection<int, GradeScale>  $gradeScales
     * @param  array{merges: Collection<int, ExamSubjectMerge>, subjectConfigs: Collection, gradeScales: Collection, subjectTypeRecords: Collection}  $mergeContext
     * @return array<string, mixed>
     */
    private function buildRow(
        StudentMeritRanking $ranking,
        Collection $subjects,
        Collection $resultsByStudent,
        Collection $gradeScales,
        bool $hasSections,
        array $mergeContext
    ): array {
        $studentResults = $resultsByStudent->get($ranking->student_id) ?? collect();

        $marks = $subjects->mapWithKeys(function (Subject $subject) use ($studentResults): array {
            $result = $studentResults->get($subject->id);
            // A result-rule result has marks even when this exam itself was missed.
            $isAbsent = $result !== null && $result->is_absent && ! $result->hasWeightedResult();

            return [$subject->id => [
                'value' => ($result && ! $isAbsent) ? $result->effective_marks : null,
                'is_absent' => $isAbsent,
            ]];
        });

        // Each merged subject's marks, keyed "merge:{id}" — only the merges
        // whose papers the student's group both takes.
        $groupId = $ranking->student?->current_group_id;
        $studentMerges = ExamSubjectMerge::applyingToGroup($mergeContext['merges'], $mergeContext['subjectTypeRecords'], $groupId !== null ? (int) $groupId : null);

        foreach ($studentMerges as $merge) {
            [$firstId, $secondId] = $merge->subjectIds();
            $first = $studentResults->get($firstId);
            $second = $studentResults->get($secondId);

            if (! $first && ! $second) {
                continue;
            }

            $outcome = $this->resolveMergedSubjectOutcome->execute(
                $merge,
                $first,
                $second,
                $mergeContext['subjectConfigs']->get($firstId),
                $mergeContext['subjectConfigs']->get($secondId),
                $mergeContext['gradeScales'],
            );

            $marks->put("merge:{$merge->id}", [
                'value' => $outcome['is_absent'] ? null : $outcome['marks'],
                'is_absent' => $outcome['is_absent'],
            ]);
        }

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

    /**
     * Sort subjects in the school-wide subject order (Subject::scopeOrdered():
     * sort_order, then id) so the tabulation sheet lists them like the marksheet.
     *
     * @param  Collection<int, Subject>  $subjects
     * @return Collection<int, Subject>
     */
    private function inSubjectOrder(Collection $subjects): Collection
    {
        return $subjects
            ->sortBy([
                fn (Subject $first, Subject $second): int => (int) $first->sort_order <=> (int) $second->sort_order,
                fn (Subject $first, Subject $second): int => (int) $first->id <=> (int) $second->id,
            ])
            ->values();
    }

    /**
     * The sheet's subject columns in order: an unmerged subject is one column;
     * a merged subject is three (1st paper | 2nd paper | merged marks) under
     * its own name, placed where its first paper would be. A merge shows only
     * when one of its papers is among the subjects.
     *
     * @param  Collection<int, Subject>  $subjects
     * @param  Collection<int, ExamSubjectMerge>  $merges
     * @param  Collection<int, ExamSubjectConfig>  $subjectConfigs  subject_id → config, for the subject's name in this exam
     * @return Collection<int, array{key: int|string, label: string, papers: list<int>}>
     */
    private function columnGroups(Collection $subjects, Collection $merges, Collection $subjectConfigs): Collection
    {
        $columns = collect();
        $emittedMergeIds = [];

        foreach ($subjects as $subject) {
            $merge = $merges->first(fn (ExamSubjectMerge $merge): bool => in_array((int) $subject->id, $merge->subjectIds(), true));

            if (! $merge) {
                $columns->push(['key' => (int) $subject->id, 'label' => $subjectConfigs->get($subject->id)?->subjectDisplayName($subject) ?? $subject->display_name, 'papers' => [(int) $subject->id]]);

                continue;
            }

            if (in_array($merge->id, $emittedMergeIds, true)) {
                continue;
            }

            $emittedMergeIds[] = $merge->id;
            [$firstId, $secondId] = $merge->subjectIds();

            $columns->push([
                'key' => "merge:{$merge->id}",
                'label' => $merge->display_name,
                'papers' => [$firstId, $secondId],
            ]);
        }

        return $columns;
    }
}
