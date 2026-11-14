<?php

namespace App\Actions;

use App\Models\ClassGroupSubject;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamSubjectMerge;
use App\Models\GradeScale;
use App\Models\StudentMeritRanking;
use App\Models\StudentOptionalSubject;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use Illuminate\Support\Collection;

class CalculateExamRankings
{
    /**
     * Grade points of the additional (4th) subject that do not count — only
     * what it earns above this is added to the GPA.
     */
    public const ADDITIONAL_SUBJECT_GRADE_POINT_DEDUCTION = 2.0;

    /**
     * Share of the additional (4th) subject's full marks that does not count
     * towards the total — 40 marks of a 100-mark subject.
     */
    public const ADDITIONAL_SUBJECT_MARKS_DEDUCTION_PERCENTAGE = 40.0;

    public const MAX_GPA = 5.0;

    public function __construct(
        private readonly CalculateWeightedResults $calculateWeightedResults,
        private readonly ResolveIsExtraOptionalSubject $resolveIsExtraOptionalSubject,
        private readonly ResolveSubjectOutcome $resolveSubjectOutcome,
        private readonly ResolveMergedSubjectOutcome $resolveMergedSubjectOutcome,
    ) {}

    public function execute(Exam $exam): int
    {
        if (! ExamSubjectConfig::where('exam_id', $exam->id)->exists()) {
            return 0;
        }

        $this->calculateWeightedResults->execute($exam);

        $studentData = $this->summarizeStudents($exam);

        if ($studentData === []) {
            return 0;
        }

        $studentIds = collect($studentData)->pluck('student_id');

        $classRankMap = $this->calculateTieredRanks(collect($studentData));
        $sectionRankMap = $this->calculateSectionRanks($studentData);

        $existingRankings = StudentMeritRanking::where('exam_id', $exam->id)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        foreach ($studentData as $data) {
            $ranking = $existingRankings->get($data['student_id'])
                ?? new StudentMeritRanking(['exam_id' => $exam->id, 'student_id' => $data['student_id']]);

            $ranking->fill([
                'class_id' => $data['class_id'],
                'section_id' => $data['section_id'],
                'total_marks' => $data['total_marks'],
                'gpa' => $data['gpa'],
                'class_rank' => $classRankMap[$data['student_id']] ?? null,
                'section_rank' => $sectionRankMap[$data['student_id']] ?? null,
            ])->save();
        }

        $this->logCalculation($exam, count($studentData));

        return count($studentData);
    }

    /**
     * Per-student result summary for this exam, read straight from the saved
     * StudentResult rows without writing anything: total marks, GPA, whether
     * the student failed overall and in how many GPA-counting subjects (a
     * merged subject counts once; an extra optional subject never counts).
     * failed_subject_keys names those subjects: "subject:{id}" or "merge:{id}".
     *
     * Used by execute() to save rankings and by the exam result report.
     *
     * @return list<array{student_id: int, class_id: int, section_id: ?int, group_id: ?int, total_marks: float, gpa: float, is_overall_fail: bool, failed_subject_count: int, failed_subject_keys: list<string>}>
     */
    public function summarizeStudents(Exam $exam): array
    {
        $subjectConfigs = ExamSubjectConfig::where('exam_id', $exam->id)
            ->get()
            ->keyBy('subject_id');

        if ($subjectConfigs->isEmpty()) {
            return [];
        }

        $results = StudentResult::where('exam_id', $exam->id)
            ->get()
            ->groupBy('student_id');

        if ($results->isEmpty()) {
            return [];
        }

        $studentIds = $results->keys()->map(fn ($id) => (int) $id);

        $profiles = StudentProfile::whereIn('id', $studentIds)
            ->get(['id', 'current_group_id', 'current_section_id'])
            ->keyBy('id');

        // subject_id → Collection of ClassGroupSubject records (grouped by subject)
        $subjectTypeRecords = ClassGroupSubject::where('class_id', $exam->class_id)
            ->get()
            ->groupBy('subject_id');

        // student_id → Collection of the student's chosen optional-subject roles for this class
        $optionalSelections = StudentOptionalSubject::where('class_id', $exam->class_id)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->groupBy('student_id');

        return $this->buildStudentData(
            $results,
            $profiles,
            $subjectConfigs,
            $subjectTypeRecords,
            $optionalSelections,
            $exam->subjectMerges()->get(),
            $exam->class_id,
            GradeScale::cached()
        );
    }

    private function logCalculation(Exam $exam, int $studentCount): void
    {
        activity('merit_ranking')
            ->performedOn($exam)
            ->event('calculated')
            ->withProperties([
                'attributes' => [
                    'exam_id' => $exam->id,
                    'exam_id_label' => $exam->displayLabel(),
                    'class_id' => $exam->class_id,
                    'class_id_label' => $exam->class?->name,
                    'session_year' => $exam->session_year,
                    'ranked_student_count' => $studentCount,
                ],
            ])
            ->log("Merit ranking calculated for {$exam->displayLabel()} — {$studentCount} students ranked.");
    }

    /**
     * Per student: total marks and GPA. Each merged subject (two papers) counts
     * as one subject — its converted marks go into the total and its single
     * grade point into the GPA; every other subject counts on its own.
     *
     * GPA = (grade points of the compulsory + main optional subjects
     * + the additional subject's grade point above 2) ÷ the number of
     * compulsory + main optional subjects, at most 5.00. The additional
     * subject adds only its marks above 40% of its full marks to the total.
     *
     * @param  Collection<int, Collection<int, StudentResult>>  $results
     * @param  Collection<int, ExamSubjectConfig>  $subjectConfigs
     * @param  Collection<int, Collection<int, ClassGroupSubject>>  $subjectTypeRecords
     * @param  Collection<int, Collection<int, StudentOptionalSubject>>  $optionalSelections
     * @param  Collection<int, ExamSubjectMerge>  $merges
     */
    private function buildStudentData(
        Collection $results,
        Collection $profiles,
        Collection $subjectConfigs,
        Collection $subjectTypeRecords,
        Collection $optionalSelections,
        Collection $merges,
        int $classId,
        Collection $gradeScales
    ): array {
        $studentData = [];
        $mergedSubjectIds = $merges->flatMap(fn (ExamSubjectMerge $merge): array => $merge->subjectIds());

        foreach ($results as $studentId => $studentResults) {
            $profile = $profiles->get((int) $studentId);
            $studentGroupId = $profile?->current_group_id
                ? (int) $profile->current_group_id
                : null;
            $studentOptionalSelections = $optionalSelections->get((int) $studentId);
            $studentResults = $studentResults->keyBy('subject_id');

            $units = [];

            foreach ($studentResults as $subjectId => $result) {
                if ($mergedSubjectIds->contains((int) $subjectId)) {
                    continue;
                }

                $config = $subjectConfigs->get($subjectId);
                $outcome = $this->resolveSubjectOutcome->execute($result, $config, $gradeScales);
                $units[] = [
                    'key' => "subject:{$subjectId}",
                    'subject_id' => (int) $subjectId,
                    'marks' => $result->effective_marks,
                    'full_marks' => $result->resolveFullMarks((float) ($config?->total_marks ?: 100)),
                ] + $outcome;
            }

            foreach ($merges as $merge) {
                [$firstId, $secondId] = $merge->subjectIds();
                $first = $studentResults->get($firstId);
                $second = $studentResults->get($secondId);

                // The student takes neither paper.
                if (! $first && ! $second) {
                    continue;
                }

                $outcome = $this->resolveMergedSubjectOutcome->execute(
                    $merge,
                    $first,
                    $second,
                    $subjectConfigs->get($firstId),
                    $subjectConfigs->get($secondId),
                    $gradeScales,
                );
                $units[] = ['key' => "merge:{$merge->id}", 'subject_id' => $first ? $firstId : $secondId] + $outcome;
            }

            $totalMarks = 0.0;
            $isOverallFail = false;
            $failedSubjectKeys = [];
            $gradePoints = [];
            $additionalBonus = 0.0;

            foreach ($units as $unit) {
                $isExtraOptional = $this->resolveIsExtraOptionalSubject->execute(
                    $subjectTypeRecords,
                    $studentOptionalSelections,
                    $unit['subject_id'],
                    $studentGroupId
                );

                if ($isExtraOptional) {
                    // Additional (4th) subject: a fail or absence adds nothing and
                    // costs nothing; a pass adds only what is above 2 grade points
                    // and above 40% of its full marks.
                    if (! $unit['is_fail']) {
                        $additionalBonus += max(0.0, (float) $unit['grade']->grade_point - self::ADDITIONAL_SUBJECT_GRADE_POINT_DEDUCTION);
                        $totalMarks += max(0.0, (float) $unit['marks'] - (float) $unit['full_marks'] * self::ADDITIONAL_SUBJECT_MARKS_DEDUCTION_PERCENTAGE / 100);
                    }

                    continue;
                }

                $totalMarks += (float) $unit['marks'];

                if ($unit['is_fail']) {
                    // Compulsory or main_optional fail (or absence) → overall fail
                    $isOverallFail = true;
                    $failedSubjectKeys[] = $unit['key'];
                }

                $gradePoints[] = $unit['is_fail'] ? 0.0 : (float) $unit['grade']->grade_point;
            }

            if ($isOverallFail || empty($gradePoints)) {
                $gpa = 0.0;
            } else {
                // Divided by the compulsory + main optional subjects only, capped at 5.00.
                $gpa = round(min(self::MAX_GPA, (array_sum($gradePoints) + $additionalBonus) / count($gradePoints)), 2);
            }

            $studentData[] = [
                'student_id' => (int) $studentId,
                'class_id' => $classId,
                'section_id' => $profile?->current_section_id,
                'group_id' => $studentGroupId,
                'total_marks' => round($totalMarks, 2),
                'gpa' => $gpa,
                'is_overall_fail' => $isOverallFail,
                'failed_subject_count' => count($failedSubjectKeys),
                'failed_subject_keys' => $failedSubjectKeys,
            ];
        }

        return $studentData;
    }

    /**
     * Two-tier ranking:
     *  - Tier 1: students who passed overall → ranked by GPA, then total_marks
     *  - Tier 2: students who failed overall (or were absent in a compulsory/main
     *    optional subject) → ranked among themselves the same way, after tier 1
     *
     * @param  Collection<int, array<string, mixed>>  $studentData
     */
    private function calculateTieredRanks(Collection $studentData): array
    {
        $passed = $this->sortForRanking($studentData->where('is_overall_fail', false));
        $failed = $this->sortForRanking($studentData->where('is_overall_fail', true));

        return $this->calculateRanks($passed, 0) + $this->calculateRanks($failed, $passed->count());
    }

    /**
     * Ranks by GPA first; when GPAs tie, total_marks breaks the tie.
     *
     * @param  Collection<int, array<string, mixed>>  $students
     */
    private function sortForRanking(Collection $students): Collection
    {
        return $students
            ->sort(fn (array $a, array $b): int => $b['gpa'] <=> $a['gpa']
                ?: $b['total_marks'] <=> $a['total_marks'])
            ->values();
    }

    /**
     * RANK() style: students tied on both GPA and total_marks share the same
     * rank, and the next rank skips accordingly (1,2,2,4).
     *
     * @param  Collection<int, array<string, mixed>>  $sorted
     */
    private function calculateRanks(Collection $sorted, int $offset = 0): array
    {
        $rankMap = [];
        $prevKey = null;
        $rank = 0;

        foreach ($sorted as $index => $data) {
            $key = [(float) $data['gpa'], (float) $data['total_marks']];

            if ($key !== $prevKey) {
                $rank = $index + 1 + $offset;
                $prevKey = $key;
            }

            $rankMap[$data['student_id']] = $rank;
        }

        return $rankMap;
    }

    /** @param array<int, array<string, mixed>> $studentData */
    private function calculateSectionRanks(array $studentData): array
    {
        $sectionRankMap = [];

        foreach (collect($studentData)->groupBy('section_id') as $sectionStudents) {
            $passed = $this->sortForRanking($sectionStudents->where('is_overall_fail', false));
            $failed = $this->sortForRanking($sectionStudents->where('is_overall_fail', true));

            $sectionRankMap += $this->calculateRanks($passed, 0) + $this->calculateRanks($failed, $passed->count());
        }

        return $sectionRankMap;
    }
}
