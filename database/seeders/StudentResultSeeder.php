<?php

namespace Database\Seeders;

use App\Enums\ExamConfigType;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class StudentResultSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $exams = Exam::with(['examType.examTypeConfig', 'subjectConfigs'])->get();

        foreach ($exams as $exam) {
            $this->seedExamResults($exam);
        }
    }

    private function seedExamResults(Exam $exam): void
    {
        $students = StudentProfile::where('current_class_id', $exam->class_id)->active()->get();

        if ($students->isEmpty()) {
            return;
        }

        $configType = $exam->examType?->examTypeConfig?->type;

        $absentCount = match ($configType) {
            ExamConfigType::Supporting => fake()->numberBetween(2, 3),
            ExamConfigType::NotSupporting => fake()->numberBetween(1, 2),
            default => 0,
        };

        $absentStudentIds = $this->pickRandomIds($students, min($absentCount, $students->count() - 1));

        foreach ($exam->subjectConfigs as $config) {
            $this->seedSubjectResults($exam, $config, $students, $absentStudentIds);
        }
    }

    /**
     * @param  Collection<int, StudentProfile>  $students
     * @param  array<int, int>  $absentStudentIds
     */
    private function seedSubjectResults(Exam $exam, ExamSubjectConfig $config, Collection $students, array $absentStudentIds): void
    {
        $presentStudents = $students->reject(fn (StudentProfile $student) => in_array($student->id, $absentStudentIds, true));

        $maxFail = min(3, max(1, intdiv($presentStudents->count(), 2)));
        $failingStudentIds = $this->pickRandomIds($presentStudents, fake()->numberBetween(1, $maxFail));

        foreach ($students as $student) {
            if (in_array($student->id, $absentStudentIds, true)) {
                $this->saveResult($exam, $config, $student, null, null, null, 0, true);

                continue;
            }

            $shouldPass = ! in_array($student->id, $failingStudentIds, true);
            [$mcqMarks, $writtenMarks, $practicalMarks, $totalMarks] = $this->randomMarks($config, $shouldPass);

            $this->saveResult($exam, $config, $student, $mcqMarks, $writtenMarks, $practicalMarks, $totalMarks, false);
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

    private function saveResult(
        Exam $exam,
        ExamSubjectConfig $config,
        StudentProfile $student,
        ?int $mcqMarks,
        ?int $writtenMarks,
        ?int $practicalMarks,
        float $totalMarks,
        bool $isAbsent
    ): void {
        StudentResult::updateOrCreate(
            [
                'exam_id' => $exam->id,
                'subject_id' => $config->subject_id,
                'student_id' => $student->id,
            ],
            [
                'class_id' => $exam->class_id,
                'mcq_marks' => $mcqMarks,
                'written_marks' => $writtenMarks,
                'practical_marks' => $practicalMarks,
                'total_marks' => $totalMarks,
                'is_absent' => $isAbsent,
            ]
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
