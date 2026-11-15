<?php

namespace App\Actions;

use App\Models\Exam;
use App\Models\ExamSubjectMerge;
use App\Models\GradeScale;
use App\Models\Group;
use App\Models\Section;
use App\Models\Subject;
use App\Support\ClassGroupSubjectDefinitions;
use Illuminate\Support\Collection;

class BuildExamResultReport
{
    public function __construct(private readonly CalculateExamRankings $calculateExamRankings) {}

    /**
     * Summary report for an exam: how many students got each GPA band
     * (5.00, 4.00-4.99, 3.50-3.99 ... down the grade scale), how many passed
     * and failed, and among the failures how many failed in 1, 2, 3...
     * subjects, and how many failed in each subject. Counts are broken down by group (Class 9-10) or by section
     * when the class has either, plus a Total column.
     *
     * Also lists the students themselves: passed students (name, roll, GPA,
     * rank) per group / section, and failed students grouped by how many
     * subjects they failed in.
     *
     * Students absent in every subject did not sit the exam: they are
     * counted only in the Total and Absent rows, and everything else (GPA
     * bands, pass/fail, failed subjects, pass rate) counts the examinees.
     *
     * Reads only after rankings exist, so the report always matches the
     * Merit Rankings / Tabulation Sheet tabs.
     *
     * @return array{
     *     exam: Exam,
     *     isEmpty: bool,
     *     breakdownLabel: ?string,
     *     columns: list<array{key: int, label: string}>,
     *     totalRow: array{label: string, total: int, by: array<int, int>},
     *     absentRow: array{label: string, total: int, by: array<int, int>},
     *     examineeRow: array{label: string, total: int, by: array<int, int>},
     *     gradeRows: list<array{label: string, total: int, by: array<int, int>}>,
     *     passedRow: array{label: string, total: int, by: array<int, int>},
     *     failedRow: array{label: string, total: int, by: array<int, int>},
     *     failRows: list<array{label: string, total: int, by: array<int, int>}>,
     *     subjectRows: list<array{label: string, total: int, by: array<int, int>}>,
     *     passedLists: list<array{label: ?string, students: list<array<string, mixed>>}>,
     *     failedLists: list<array{label: string, students: list<array<string, mixed>>}>,
     *     passRate: float,
     * }
     */
    public function handle(Exam $exam): array
    {
        $exam->loadMissing(['examType', 'class']);

        $students = $exam->meritRankings()->exists()
            ? collect($this->calculateExamRankings->summarizeStudents($exam))
            : collect();

        [$breakdownLabel, $breakdownKey, $columns] = $this->resolveBreakdown($exam, $students);

        $countRow = fn (string $label, Collection $matching): array => [
            'label' => $label,
            'total' => $matching->count(),
            'by' => collect($columns)->mapWithKeys(fn (array $column): array => [
                $column['key'] => $matching->where($breakdownKey, $column['key'])->count(),
            ])->all(),
        ];

        $absentees = $students->where('is_fully_absent', true);
        $examinees = $students->where('is_fully_absent', false);
        $passed = $examinees->where('is_overall_fail', false);
        $failed = $examinees->where('is_overall_fail', true);

        $gradeScales = GradeScale::cached();

        $gradeRows = [];
        $higherGradePoint = null;

        foreach ($this->passingGrades($gradeScales) as $grade) {
            $gradeRows[] = $countRow(
                $this->gpaBandLabel($grade, $higherGradePoint),
                $passed->filter(fn (array $student): bool => GradeScale::fromGpa((float) $student['gpa'], $gradeScales)?->id === $grade->id),
            );

            $higherGradePoint = (float) $grade->grade_point;
        }

        $failRows = [];

        foreach (range(1, max(1, (int) $failed->max('failed_subject_count'))) as $subjectCount) {
            $failRows[] = $countRow(
                $subjectCount === 1 ? 'Failed in 1 subject' : "Failed in {$subjectCount} subjects",
                $failed->where('failed_subject_count', $subjectCount),
            );
        }

        $subjectLabels = $this->reportSubjects($exam);
        $studentRows = $this->studentRows($exam, $examinees, $breakdownKey, $columns, $subjectLabels);

        return [
            'exam' => $exam,
            'isEmpty' => $students->isEmpty(),
            'passedLists' => $this->passedLists($studentRows->where('is_fail', false), $breakdownKey, $columns),
            'failedLists' => $studentRows
                ->where('is_fail', true)
                ->groupBy('failed_subject_count')
                ->sortKeys()
                ->map(fn (Collection $rows, int $subjectCount): array => [
                    'label' => $subjectCount === 1 ? 'Failed in 1 subject' : "Failed in {$subjectCount} subjects",
                    'students' => $rows->values()->all(),
                ])
                ->values()
                ->all(),
            'breakdownLabel' => $breakdownLabel,
            'columns' => $columns,
            'totalRow' => $countRow('Total Students', $students),
            'absentRow' => $countRow('Absent (All Subjects)', $absentees),
            'examineeRow' => $countRow('Examinees', $examinees),
            'gradeRows' => $gradeRows,
            'passedRow' => $countRow('Passed', $passed),
            'failedRow' => $countRow('Failed', $failed),
            'failRows' => $failRows,
            'subjectRows' => collect($subjectLabels)
                ->map(fn (string $label, string $key): array => $countRow(
                    $label,
                    $failed->filter(fn (array $student): bool => in_array($key, $student['failed_subject_keys'], true)),
                ))
                ->values()
                ->all(),
            'passRate' => $examinees->isEmpty() ? 0.0 : round($passed->count() / $examinees->count() * 100, 2),
        ];
    }

    /**
     * One row per examinee for the passed / failed student lists, in merit
     * order (class rank, then roll).
     *
     * @param  Collection<int, array<string, mixed>>  $examinees
     * @param  list<array{key: int, label: string}>  $columns
     * @param  array<string, string>  $subjectLabels
     * @return Collection<int, array{name: string, roll: ?string, breakdown: ?string, breakdown_key: ?int, gpa: float, class_rank: ?int, section_rank: ?int, is_fail: bool, failed_subject_count: int, failed_subjects: string}>
     */
    private function studentRows(Exam $exam, Collection $examinees, ?string $breakdownKey, array $columns, array $subjectLabels): Collection
    {
        $rankings = $exam->meritRankings()
            ->with('student.user')
            ->whereIn('student_id', $examinees->pluck('student_id'))
            ->get()
            ->keyBy('student_id');

        $columnLabels = collect($columns)->pluck('label', 'key');

        return $examinees
            ->map(function (array $student) use ($rankings, $breakdownKey, $columnLabels, $subjectLabels): array {
                $ranking = $rankings->get($student['student_id']);

                return [
                    'name' => $ranking?->student?->user?->name ?? '—',
                    'roll' => $ranking?->student?->roll_no !== null ? (string) $ranking->student->roll_no : null,
                    'breakdown' => $breakdownKey ? $columnLabels->get($student[$breakdownKey]) : null,
                    'breakdown_key' => $breakdownKey ? $student[$breakdownKey] : null,
                    'gpa' => (float) $student['gpa'],
                    'class_rank' => $ranking?->class_rank !== null ? (int) $ranking->class_rank : null,
                    'section_rank' => $ranking?->section_rank !== null ? (int) $ranking->section_rank : null,
                    'is_fail' => (bool) $student['is_overall_fail'],
                    'failed_subject_count' => (int) $student['failed_subject_count'],
                    'failed_subjects' => collect($student['failed_subject_keys'])
                        ->map(fn (string $key): ?string => $subjectLabels[$key] ?? null)
                        ->filter()
                        ->implode(', '),
                ];
            })
            ->sortBy([
                fn (array $a, array $b): int => ($a['class_rank'] ?? PHP_INT_MAX) <=> ($b['class_rank'] ?? PHP_INT_MAX),
                fn (array $a, array $b): int => (int) $a['roll'] <=> (int) $b['roll'],
            ])
            ->values();
    }

    /**
     * Passed students, one list per group / section (each its own PDF page),
     * or a single list when the class has neither.
     *
     * @param  Collection<int, array<string, mixed>>  $passedRows
     * @param  list<array{key: int, label: string}>  $columns
     * @return list<array{label: ?string, students: list<array<string, mixed>>}>
     */
    private function passedLists(Collection $passedRows, ?string $breakdownKey, array $columns): array
    {
        if (! $breakdownKey || $columns === []) {
            return [['label' => null, 'students' => $passedRows->values()->all()]];
        }

        return collect($columns)
            ->map(fn (array $column): array => [
                'label' => $column['label'],
                'students' => $passedRows->where('breakdown_key', $column['key'])->values()->all(),
            ])
            ->all();
    }

    /**
     * The exam's subjects in the school's subject order, keyed like
     * CalculateExamRankings' failed_subject_keys: a merged subject (1st + 2nd
     * paper) is one entry under its own name, placed at its first paper.
     *
     * @return array<string, string> key ("subject:{id}" / "merge:{id}") => subject name
     */
    private function reportSubjects(Exam $exam): array
    {
        $merges = $exam->subjectMerges()->get();

        $subjects = Subject::query()
            ->whereIn('id', $exam->subjectConfigs()->select('subject_id'))
            ->ordered()
            ->get();

        $entries = [];

        foreach ($subjects as $subject) {
            $merge = $merges->first(fn (ExamSubjectMerge $merge): bool => in_array((int) $subject->id, $merge->subjectIds(), true));

            if ($merge) {
                $entries["merge:{$merge->id}"] ??= $merge->display_name;

                continue;
            }

            $entries["subject:{$subject->id}"] = $subject->display_name;
        }

        return $entries;
    }

    /**
     * Grades a passing student can hold, highest grade point first (F excluded).
     *
     * @param  Collection<int, GradeScale>  $gradeScales
     * @return Collection<int, GradeScale>
     */
    private function passingGrades(Collection $gradeScales): Collection
    {
        $failing = GradeScale::failing($gradeScales);

        return $gradeScales
            ->reject(fn (GradeScale $grade): bool => $grade->id === $failing?->id)
            ->sortByDesc('grade_point')
            ->values();
    }

    /**
     * "GPA 5.00 (A+)" for the top grade, "GPA 4.00 - 4.99 (A)" for the rest.
     */
    private function gpaBandLabel(GradeScale $grade, ?float $higherGradePoint): string
    {
        $lower = number_format((float) $grade->grade_point, 2);

        $range = $higherGradePoint === null
            ? $lower
            : $lower.' - '.number_format($higherGradePoint - 0.01, 2);

        return "GPA {$range} ({$grade->letter_grade})";
    }

    /**
     * Which column set to split the counts by: groups for a grouped class,
     * otherwise sections when the class has any, otherwise none.
     *
     * @param  Collection<int, array<string, mixed>>  $students
     * @return array{0: ?string, 1: ?string, 2: list<array{key: int, label: string}>}
     */
    private function resolveBreakdown(Exam $exam, Collection $students): array
    {
        $class = $exam->class;

        if ($class?->has_group) {
            $columns = $class->groups
                ->sortBy(fn (Group $group): int => (int) array_search($group->name, ClassGroupSubjectDefinitions::SECONDARY_GROUPS, true))
                ->filter(fn (Group $group): bool => $students->contains('group_id', $group->id))
                ->map(fn (Group $group): array => ['key' => (int) $group->id, 'label' => $group->name])
                ->values()
                ->all();

            return ['Group', 'group_id', $columns];
        }

        $sections = $class?->sections()->orderBy('name')->get() ?? collect();

        if ($sections->isNotEmpty()) {
            $columns = $sections
                ->filter(fn (Section $section): bool => $students->contains('section_id', $section->id))
                ->map(fn (Section $section): array => ['key' => (int) $section->id, 'label' => $section->name])
                ->values()
                ->all();

            return ['Section', 'section_id', $columns];
        }

        return [null, null, []];
    }
}
