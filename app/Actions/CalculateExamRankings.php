<?php

namespace App\Actions;

use App\Enums\Grade;
use App\Enums\SubjectType;
use App\Models\ClassGroupSubject;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use Illuminate\Support\Collection;

class CalculateExamRankings
{
    public function execute(Exam $exam): int
    {
        $subjectConfigs = ExamSubjectConfig::where('exam_id', $exam->id)
            ->get()
            ->keyBy('subject_id');

        if ($subjectConfigs->isEmpty()) {
            return 0;
        }

        $results = StudentResult::where('exam_id', $exam->id)
            ->get()
            ->groupBy('student_id');

        if ($results->isEmpty()) {
            return 0;
        }

        $studentIds = $results->keys()->map(fn ($id) => (int) $id);

        $profiles = StudentProfile::whereIn('id', $studentIds)
            ->get()
            ->keyBy('id');

        // subject_id → Collection of ClassGroupSubject records (grouped by subject)
        $subjectTypeRecords = ClassGroupSubject::where('class_id', $exam->class_id)
            ->get()
            ->groupBy('subject_id');

        $studentData = $this->buildStudentData(
            $results,
            $profiles,
            $subjectConfigs,
            $subjectTypeRecords,
            $exam->class_id
        );

        $classRankMap = $this->calculateTieredRanks(collect($studentData));
        $sectionRankMap = $this->calculateSectionRanks($studentData);

        foreach ($studentData as $data) {
            StudentMeritRanking::updateOrCreate(
                [
                    'exam_id' => $exam->id,
                    'student_id' => $data['student_id'],
                ],
                [
                    'class_id' => $data['class_id'],
                    'section_id' => $data['section_id'],
                    'total_marks' => $data['total_marks'],
                    'gpa' => $data['gpa'],
                    'class_rank' => $classRankMap[$data['student_id']] ?? null,
                    'section_rank' => $sectionRankMap[$data['student_id']] ?? null,
                ]
            );
        }

        return count($studentData);
    }

    /**
     * @param  Collection<int, Collection<int, StudentResult>>  $results
     * @param  Collection<int, ExamSubjectConfig>  $subjectConfigs
     * @param  Collection<int, Collection<int, ClassGroupSubject>>  $subjectTypeRecords
     */
    private function buildStudentData(
        Collection $results,
        Collection $profiles,
        Collection $subjectConfigs,
        Collection $subjectTypeRecords,
        int $classId
    ): array {
        $studentData = [];

        foreach ($results as $studentId => $studentResults) {
            $profile = $profiles->get((int) $studentId);
            $studentGroupId = $profile?->current_group_id
                ? (int) $profile->current_group_id
                : null;

            $totalMarks = $studentResults->sum('total_marks');
            $hasAnyAbsent = $studentResults->contains(fn (StudentResult $r) => $r->is_absent);

            $isOverallFail = false;
            $includedGpas = [];

            foreach ($studentResults as $result) {
                $subjectType = $this->resolveSubjectType(
                    $subjectTypeRecords,
                    (int) $result->subject_id,
                    $studentGroupId
                );

                if ($result->is_absent) {
                    // Absent counts as F; extra_optional absence is excluded entirely
                    if ($subjectType !== SubjectType::ExtraOptional) {
                        $isOverallFail = true;
                    }

                    continue;
                }

                $config = $subjectConfigs->get($result->subject_id);
                $fullMarks = $config?->total_marks ?? 100;
                $percentage = $fullMarks > 0
                    ? ($result->total_marks / $fullMarks) * 100
                    : 0.0;

                $grade = Grade::fromMarks($percentage);

                if ($grade === Grade::F) {
                    if ($subjectType === SubjectType::ExtraOptional) {
                        // Extra optional fail → exclude subject from GPA, no penalty
                        continue;
                    }
                    // Compulsory or main_optional fail → overall fail
                    $isOverallFail = true;
                }

                $includedGpas[] = $grade->gpa();
            }

            if ($isOverallFail || empty($includedGpas)) {
                $gpa = 0.0;
            } else {
                $gpa = round(array_sum($includedGpas) / count($includedGpas), 2);
            }

            $studentData[] = [
                'student_id' => (int) $studentId,
                'class_id' => $classId,
                'section_id' => $profile?->current_section_id,
                'total_marks' => (float) $totalMarks,
                'gpa' => $gpa,
                'has_any_absent' => $hasAnyAbsent,
            ];
        }

        return $studentData;
    }

    /**
     * Resolve subject type for a student, preferring their specific group,
     * falling back to the "all groups" record (group_id = null).
     *
     * @param  Collection<int, Collection<int, ClassGroupSubject>>  $subjectTypeRecords
     */
    private function resolveSubjectType(
        Collection $subjectTypeRecords,
        int $subjectId,
        ?int $groupId
    ): SubjectType {
        $records = $subjectTypeRecords->get($subjectId);

        if (! $records || $records->isEmpty()) {
            return SubjectType::Compulsory;
        }

        $match = $records->first(fn (ClassGroupSubject $r) => $r->group_id !== null && $r->group_id === $groupId)
            ?? $records->first(fn (ClassGroupSubject $r) => $r->group_id === null);

        return $match?->subject_type ?? SubjectType::Compulsory;
    }

    /**
     * Two-tier ranking:
     *  - Tier 1: students present in ALL subjects → ranked by total_marks
     *  - Tier 2: students absent in ANY subject → ranked by total_marks, after tier 1
     *
     * @param  Collection<int, array<string, mixed>>  $studentData
     */
    private function calculateTieredRanks(Collection $studentData): array
    {
        $present = $studentData->where('has_any_absent', false)->sortByDesc('total_marks')->values();
        $absent = $studentData->where('has_any_absent', true)->sortByDesc('total_marks')->values();

        return $this->calculateRanks($present, 0) + $this->calculateRanks($absent, $present->count());
    }

    /**
     * RANK() style: ties share the same rank, next rank skips (1,2,2,4).
     *
     * @param  Collection<int, array<string, mixed>>  $sorted
     */
    private function calculateRanks(Collection $sorted, int $offset = 0): array
    {
        $rankMap = [];
        $prevTotal = null;
        $rank = 0;

        foreach ($sorted as $index => $data) {
            $marks = (float) $data['total_marks'];

            if ($marks !== $prevTotal) {
                $rank = $index + 1 + $offset;
                $prevTotal = $marks;
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
            $present = $sectionStudents->where('has_any_absent', false)->sortByDesc('total_marks')->values();
            $absent = $sectionStudents->where('has_any_absent', true)->sortByDesc('total_marks')->values();

            $sectionRankMap += $this->calculateRanks($present, 0) + $this->calculateRanks($absent, $present->count());
        }

        return $sectionRankMap;
    }
}
