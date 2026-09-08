<?php

namespace Database\Seeders;

use App\Actions\ResolveEligibleStudentsForSubject;
use App\Enums\ExamConfigType;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class StudentResultSeeder extends Seeder
{
    public function __construct(
        private readonly ResolveEligibleStudentsForSubject $resolveEligibleStudentsForSubject,
    ) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $exams = Exam::with(['examType.examTypeConfig', 'subjectConfigs'])->get();

        DB::transaction(function () use ($exams) {
            foreach ($exams as $exam) {
                $this->seedExamResults($exam);
            }
        });
    }

    private function seedExamResults(Exam $exam): void
    {
        $subjectConfigs = $exam->subjectConfigs;

        if ($subjectConfigs->isEmpty()) {
            return;
        }

        // Class 9-10 splits into Science/Commerce/Humanities groups: some subjects
        // are group-restricted compulsory subjects and others are per-student
        // optional picks, so who actually sits a subject's exam varies per student.
        // Mirror the same eligibility rules used for the real marks-entry sheet
        // instead of assuming every student in the class takes every subject.
        $eligibleStudentsBySubject = $subjectConfigs->mapWithKeys(
            fn (ExamSubjectConfig $config): array => [
                $config->subject_id => $this->resolveEligibleStudentsForSubject->execute((int) $exam->class_id, (int) $config->subject_id),
            ]
        );

        $eligibleSubjectIdsByStudent = [];

        foreach ($eligibleStudentsBySubject as $subjectId => $eligibleStudents) {
            foreach ($eligibleStudents as $student) {
                $eligibleSubjectIdsByStudent[$student->id][] = $subjectId;
            }
        }

        if ($eligibleSubjectIdsByStudent === []) {
            return;
        }

        $studentsWithSubjects = StudentProfile::where('current_class_id', $exam->class_id)
            ->active()
            ->get()
            ->filter(fn (StudentProfile $student): bool => isset($eligibleSubjectIdsByStudent[$student->id]))
            ->values();

        $configType = $exam->examType?->examTypeConfig?->type;

        $absentCount = match ($configType) {
            ExamConfigType::Supporting => fake()->numberBetween(1, 2),
            ExamConfigType::NotSupporting => fake()->numberBetween(1, 2),
            default => 0,
        };

        $absentStudentIds = $this->pickRandomIds($studentsWithSubjects, min($absentCount, $studentsWithSubjects->count() - 1));

        // Each absent student misses only one subject of the exam they're actually
        // eligible for, not every subject.
        $absentSubjectIdByStudent = collect($absentStudentIds)
            ->mapWithKeys(fn (int $studentId): array => [$studentId => collect($eligibleSubjectIdsByStudent[$studentId])->random()]);

        // At least 99% of students must pass each exam — at most one student may
        // fail it, and only in a single eligible subject; even that is not guaranteed.
        $presentStudents = $studentsWithSubjects->reject(fn (StudentProfile $student) => in_array($student->id, $absentStudentIds, true));

        $failingStudentId = ($presentStudents->isNotEmpty() && fake()->boolean(40))
            ? $presentStudents->random()->id
            : null;

        $failingSubjectId = $failingStudentId ? collect($eligibleSubjectIdsByStudent[$failingStudentId])->random() : null;

        $rows = [];

        foreach ($subjectConfigs as $config) {
            $eligibleStudents = $eligibleStudentsBySubject->get($config->subject_id);

            if ($eligibleStudents === null || $eligibleStudents->isEmpty()) {
                continue;
            }

            $this->collectSubjectResults($rows, $exam, $config, $eligibleStudents, $absentSubjectIdByStudent, $failingStudentId, $failingSubjectId);
        }

        $this->upsertResults($rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, StudentProfile>  $students
     * @param  SupportCollection<int, int>  $absentSubjectIdByStudent  Subject the student is absent for, keyed by student ID.
     */
    private function collectSubjectResults(
        array &$rows,
        Exam $exam,
        ExamSubjectConfig $config,
        Collection $students,
        SupportCollection $absentSubjectIdByStudent,
        ?int $failingStudentId,
        ?int $failingSubjectId,
    ): void {
        foreach ($students as $student) {
            if ($absentSubjectIdByStudent->get($student->id) === $config->subject_id) {
                $rows[] = $this->resultRow($exam, $config, $student, null, null, null, 0, true);

                continue;
            }

            $failsThisSubject = $student->id === $failingStudentId && $config->subject_id === $failingSubjectId;
            [$mcqMarks, $writtenMarks, $practicalMarks, $totalMarks] = $this->randomMarks($config, ! $failsThisSubject);

            $rows[] = $this->resultRow($exam, $config, $student, $mcqMarks, $writtenMarks, $practicalMarks, $totalMarks, false);
        }
    }

    /**
     * @return array{0: ?int, 1: ?int, 2: ?int, 3: float}
     */
    private function randomMarks(ExamSubjectConfig $config, bool $shouldPass): array
    {
        $passMark = (int) $config->pass_mark;
        $totalMarks = (int) $config->total_marks;

        $target = $shouldPass
            ? fake()->numberBetween($passMark, $totalMarks)
            : fake()->numberBetween(0, max($passMark - 1, 0));

        $components = [
            'mcq' => $config->mcq_total !== null ? (int) $config->mcq_total : null,
            'written' => $config->written_total !== null ? (int) $config->written_total : null,
            'practical' => $config->practical_total !== null ? (int) $config->practical_total : null,
        ];

        $marks = ['mcq' => null, 'written' => null, 'practical' => null];
        $remainingTarget = $target;
        $remainingTotals = array_sum(array_filter($components));

        foreach ($components as $key => $componentTotal) {
            if (! $componentTotal) {
                continue;
            }

            $remainingTotals -= $componentTotal;
            $lower = max(0, $remainingTarget - $remainingTotals);
            $upper = min($componentTotal, $remainingTarget);

            $marks[$key] = fake()->numberBetween($lower, $upper);
            $remainingTarget -= $marks[$key];
        }

        $total = array_sum(array_filter($marks, fn (?int $value) => $value !== null));

        return [$marks['mcq'], $marks['written'], $marks['practical'], (float) $total];
    }

    /**
     * @return array<string, mixed>
     */
    private function resultRow(
        Exam $exam,
        ExamSubjectConfig $config,
        StudentProfile $student,
        ?int $mcqMarks,
        ?int $writtenMarks,
        ?int $practicalMarks,
        float $totalMarks,
        bool $isAbsent
    ): array {
        return [
            'exam_id' => $exam->id,
            'subject_id' => $config->subject_id,
            'student_id' => $student->id,
            'class_id' => $exam->class_id,
            'mcq_marks' => $mcqMarks,
            'written_marks' => $writtenMarks,
            'practical_marks' => $practicalMarks,
            'total_marks' => $totalMarks,
            'is_absent' => $isAbsent,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function upsertResults(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        collect($rows)->chunk(500)->each(
            fn (SupportCollection $chunk) => StudentResult::upsert(
                $chunk->all(),
                ['exam_id', 'subject_id', 'student_id'],
                ['class_id', 'mcq_marks', 'written_marks', 'practical_marks', 'total_marks', 'is_absent', 'updated_at']
            )
        );
    }

    /**
     * @param  Collection<int, StudentProfile>  $students
     * @return array<int, int>
     */
    private function pickRandomIds(Collection $students, int $count): array
    {
        if ($count <= 0 || $students->isEmpty()) {
            return [];
        }

        return $students->random(min($count, $students->count()))->pluck('id')->all();
    }
}
