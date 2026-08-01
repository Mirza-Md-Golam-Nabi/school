<?php

namespace Database\Seeders;

use App\Enums\CountMethod;
use App\Enums\ExamConfigType;
use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Models\Exam;
use App\Models\ExamContributeRule;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\ExamTypeConfig;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * TEMPORARY — for manually testing the Marksheet feature. Not registered in
 * DatabaseSeeder. Delete this file once marksheet testing is done.
 *
 * Creates a "Class Test" exam type (Supporting, Best 2 of N) that contributes
 * 30 marks into a "1st Semester" exam type (Not Supporting, created here) for
 * every class from 1 to 10.
 */
class MarksheetTestingSeeder extends Seeder
{
    private const SOURCE_NAME = 'Class Test';

    private const TARGET_NAME = '1st Semester';

    private const CONTRIBUTION_PERCENT = 30;

    private const BEST_N_COUNT = 2;

    private const CLASS_TEST_WRITTEN_MARKS = 15;

    private const SEMESTER_WRITTEN_MARKS = 70;

    public function run(): void
    {
        $sessionYear = (int) now()->year;

        $source = ExamType::firstOrCreate(
            ['name' => self::SOURCE_NAME],
            ['is_active' => true]
        );

        ExamTypeConfig::updateOrCreate(
            ['exam_type_id' => $source->id],
            [
                'type' => ExamConfigType::Supporting,
                'count_method' => CountMethod::BestN,
                'best_n_count' => self::BEST_N_COUNT,
            ]
        );

        $target = ExamType::firstOrCreate(
            ['name' => self::TARGET_NAME],
            ['is_active' => true]
        );

        ExamTypeConfig::updateOrCreate(
            ['exam_type_id' => $target->id],
            [
                'type' => ExamConfigType::NotSupporting,
                'count_method' => CountMethod::All,
                'best_n_count' => null,
            ]
        );

        $classes = Classes::whereIn('name', collect(range(1, 10))->map(fn (int $n) => "Class {$n}"))->get();

        foreach ($classes as $class) {
            ExamContributeRule::updateOrCreate(
                [
                    'class_id' => $class->id,
                    'source_exam_type_id' => $source->id,
                    'target_exam_type_id' => $target->id,
                    'session_year' => $sessionYear,
                ],
                ['contribution_percent' => self::CONTRIBUTION_PERCENT]
            );
        }

        $this->command?->info(
            self::SOURCE_NAME.' → '.self::TARGET_NAME.' ('.self::CONTRIBUTION_PERCENT.'%, best '.self::BEST_N_COUNT.") contribution rule seeded for {$classes->count()} classes."
        );

        // Three Class Test sittings — best_n_count = 2, so the contribution logic
        // has 3 instances to pick the best 2 from.
        $dateRanges = [
            ['start' => "{$sessionYear}-02-01", 'end' => "{$sessionYear}-02-05"],
            ['start' => "{$sessionYear}-03-01", 'end' => "{$sessionYear}-03-05"],
            ['start' => "{$sessionYear}-04-01", 'end' => "{$sessionYear}-04-05"],
        ];

        foreach ($dateRanges as $dateRange) {
            ['exam' => $exam, 'subjects' => $subjects, 'class' => $classOne] = $this->seedExamForClassOne(
                $source,
                $sessionYear,
                $dateRange['start'],
                $dateRange['end'],
                self::CLASS_TEST_WRITTEN_MARKS
            );

            $this->seedClassOneStudentMarks($exam, $subjects, $classOne, self::CLASS_TEST_WRITTEN_MARKS);
        }

        // 1st Semester (target) exam — assumed to sit right after the 3 Class
        // Tests; adjust these dates if a different sitting was intended.
        ['exam' => $semesterExam, 'subjects' => $semesterSubjects, 'class' => $classOne] = $this->seedExamForClassOne(
            $target,
            $sessionYear,
            "{$sessionYear}-04-19",
            "{$sessionYear}-04-23",
            self::SEMESTER_WRITTEN_MARKS
        );

        $this->seedClassOneStudentMarks($semesterExam, $semesterSubjects, $classOne, self::SEMESTER_WRITTEN_MARKS);
    }

    /**
     * Create an exam for Class 1 over the given date range, and a Written-only
     * subject config (at $writtenMarks) for every subject Class 1 has.
     *
     * @return array{exam: Exam, subjects: Collection<int, Subject>, class: Classes}
     */
    private function seedExamForClassOne(ExamType $examType, int $sessionYear, string $startDate, string $endDate, int $writtenMarks): array
    {
        $classOne = Classes::where('name', 'Class 1')->firstOrFail();

        // Exam stores `start_date` via the model's default datetime format ("Y-m-d
        // H:i:s"), so a plain "Y-m-d" string never matches on a plain firstOrCreate()
        // WHERE lookup (it would silently create a duplicate exam on every re-run).
        // whereDate() compares only the date portion, so it's immune to that mismatch.
        $exam = Exam::where('exam_type_id', $examType->id)
            ->where('class_id', $classOne->id)
            ->where('session_year', $sessionYear)
            ->whereDate('start_date', $startDate)
            ->first();

        if (! $exam) {
            $exam = Exam::create([
                'exam_type_id' => $examType->id,
                'class_id' => $classOne->id,
                'session_year' => $sessionYear,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_published' => false,
            ]);
        }

        $subjectIds = ClassGroupSubject::where('class_id', $classOne->id)
            ->whereNull('group_id')
            ->pluck('subject_id');

        $subjects = Subject::whereIn('id', $subjectIds)->get();

        foreach ($subjects as $subject) {
            ExamSubjectConfig::updateOrCreate(
                [
                    'exam_id' => $exam->id,
                    'subject_id' => $subject->id,
                ],
                [
                    'mcq_total' => null,
                    'mcq_pass_mark' => null,
                    'written_total' => $writtenMarks,
                    'written_pass_mark' => null,
                    'practical_total' => null,
                    'practical_pass_mark' => null,
                    'total_marks' => $writtenMarks,
                    'pass_mark' => (int) round($writtenMarks * 0.33),
                    'check_mcq_pass' => false,
                    'check_written_pass' => false,
                    'check_practical_pass' => false,
                ]
            );
        }

        $this->command?->info(
            "{$examType->name} exam created for Class 1 ({$startDate} to {$endDate}) with ".
            "{$subjects->count()} subject(s) configured for {$writtenMarks} marks (Written only)."
        );

        return ['exam' => $exam, 'subjects' => $subjects, 'class' => $classOne];
    }

    /**
     * Give every active student in Class 1 a random written mark (between 33%
     * and 100% of the subject total) for each of the given subjects.
     *
     * @param  Collection<int, Subject>  $subjects
     */
    private function seedClassOneStudentMarks(Exam $exam, Collection $subjects, Classes $classOne, int $writtenMarks): void
    {
        $minMarks = (int) round($writtenMarks * 0.33);
        $maxMarks = $writtenMarks;

        $students = StudentProfile::active()
            ->where('current_class_id', $classOne->id)
            ->get(['id']);

        foreach ($students as $student) {
            foreach ($subjects as $subject) {
                StudentResult::firstOrCreate(
                    [
                        'exam_id' => $exam->id,
                        'subject_id' => $subject->id,
                        'student_id' => $student->id,
                    ],
                    [
                        'class_id' => $classOne->id,
                        'written_marks' => random_int($minMarks, $maxMarks),
                        'is_absent' => false,
                    ]
                );
            }
        }

        $this->command?->info(
            "Written marks (random {$minMarks}-{$maxMarks}) seeded for {$students->count()} student(s) ".
            "across {$subjects->count()} subject(s)."
        );
    }
}
