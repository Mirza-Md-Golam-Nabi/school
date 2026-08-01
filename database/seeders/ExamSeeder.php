<?php

namespace Database\Seeders;

use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExamSeeder extends Seeder
{
    /**
     * Date ranges for each exam type in the given session year.
     *
     * @return array<string, array<int, array{start: string, end: string}>>
     */
    private function examSchedule(int $year): array
    {
        return [
            'Half Yearly' => [
                ['start' => "{$year}-06-01", 'end' => "{$year}-06-15"],
            ],
            'Annual' => [
                ['start' => "{$year}-11-01", 'end' => "{$year}-11-15"],
            ],
            'Tutorial' => [
                ['start' => "{$year}-02-10", 'end' => "{$year}-02-15"],
                ['start' => "{$year}-04-20", 'end' => "{$year}-04-25"],
                ['start' => "{$year}-07-15", 'end' => "{$year}-07-20"],
                ['start' => "{$year}-09-20", 'end' => "{$year}-09-25"],
            ],
        ];
    }

    public function run(): void
    {
        DB::transaction(function () {
            $sessionYear = (int) now()->year;
            $schedule = $this->examSchedule($sessionYear);
            $examTypes = ExamType::pluck('id', 'name');
            $classes = Classes::whereNull('deleted_at')->get();

            foreach ($classes as $class) {
                $subjectIds = ClassGroupSubject::where('class_id', $class->id)
                    ->whereNull('group_id')
                    ->pluck('subject_id');

                $subjects = Subject::whereIn('id', $subjectIds)->get();

                foreach ($schedule as $examTypeName => $dateRanges) {
                    $examTypeId = $examTypes->get($examTypeName);

                    if (! $examTypeId) {
                        continue;
                    }

                    foreach ($dateRanges as $dateRange) {
                        $exam = $this->findOrCreateExam($examTypeId, $class->id, $sessionYear, $dateRange);

                        foreach ($subjects as $subject) {
                            $this->seedSubjectConfig($exam, $subject, $examTypeName);
                        }
                    }
                }
            }
        });
    }

    /**
     * @param  array{start: string, end: string}  $dateRange
     */
    private function findOrCreateExam(int $examTypeId, int $classId, int $sessionYear, array $dateRange): Exam
    {
        // Exam stores `start_date` via the model's default datetime format ("Y-m-d
        // H:i:s"), so a plain "Y-m-d" string never matches on a plain firstOrCreate()
        // WHERE lookup (it would silently create a duplicate exam on every re-run).
        // whereDate() compares only the date portion, so it's immune to that mismatch.
        $exam = Exam::where('exam_type_id', $examTypeId)
            ->where('class_id', $classId)
            ->where('session_year', $sessionYear)
            ->whereDate('start_date', $dateRange['start'])
            ->first();

        return $exam ?? Exam::create([
            'exam_type_id' => $examTypeId,
            'class_id' => $classId,
            'session_year' => $sessionYear,
            'start_date' => $dateRange['start'],
            'end_date' => $dateRange['end'],
            'is_published' => false,
        ]);
    }

    private function seedSubjectConfig(Exam $exam, Subject $subject, string $examTypeName): void
    {
        $isTutorial = $examTypeName === 'Tutorial';

        if ($subject->has_mcq) {
            $mcqTotal = $isTutorial ? 15 : 30;
            $mcqPassMark = $isTutorial ? 6 : 12;
            $writtenTotal = $isTutorial ? 35 : 70;
            $writtenPass = $isTutorial ? 14 : 28;
        } else {
            $mcqTotal = null;
            $mcqPassMark = null;
            $writtenTotal = $isTutorial ? 50 : 100;
            $writtenPass = $isTutorial ? 20 : 40;
        }

        $totalMarks = ($mcqTotal ?? 0) + $writtenTotal;
        $passMarks = $isTutorial ? 20 : 40;

        ExamSubjectConfig::firstOrCreate(
            [
                'exam_id' => $exam->id,
                'subject_id' => $subject->id,
            ],
            [
                'mcq_total' => $mcqTotal,
                'mcq_pass_mark' => $mcqPassMark,
                'written_total' => $writtenTotal,
                'written_pass_mark' => $writtenPass,
                'practical_total' => null,
                'practical_pass_mark' => null,
                'total_marks' => $totalMarks,
                'pass_mark' => $passMarks,
                'check_mcq_pass' => $subject->has_mcq,
                'check_written_pass' => true,
                'check_practical_pass' => false,
            ]
        );
    }
}
