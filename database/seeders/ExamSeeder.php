<?php

namespace Database\Seeders;

use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\Subject;
use App\Services\WorkingDaysCalculator;
use Database\Seeders\Helpers\AttendanceSeedHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ExamSeeder extends Seeder
{
    /**
     * Date ranges for each exam type in the given session year — the
     * anchor each exam's subject schedule starts walking from. The actual
     * start_date/end_date stored on the exam are derived from where the
     * per-subject schedule actually lands (see seedExamSchedule()), since
     * Friday/Saturday are skipped and a subject-heavy class may need more
     * days than this range allows — no subject is ever dropped to fit it.
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
        AttendanceSeedHelper::ensureWeekendDaysConfigured();

        DB::transaction(function () {
            $sessionYear = (int) now()->year;
            $schedule = $this->examSchedule($sessionYear);
            $examTypes = ExamType::pluck('id', 'name');
            $classes = Classes::whereNull('deleted_at')->get();
            $calculator = new WorkingDaysCalculator;

            // Half Yearly itself, and every Tutorial round held before it, are treated as
            // already conducted and published; Annual and the later Tutorial rounds are
            // still upcoming, so they stay unpublished.
            $halfYearlyStart = $schedule['Half Yearly'][0]['start'] ?? null;

            foreach ($classes as $class) {
                // Every subject offered to this class — compulsory or optional, for a
                // specific group or for all groups — must get its own exam schedule, so a
                // Science-only subject like Physics is never left out of Class 9-10's exam.
                $subjectIds = ClassGroupSubject::where('class_id', $class->id)
                    ->pluck('subject_id')
                    ->unique();

                $subjects = Subject::whereIn('id', $subjectIds)->orderBy('name')->get();

                if ($subjects->isEmpty()) {
                    continue;
                }

                foreach ($schedule as $examTypeName => $dateRanges) {
                    $examTypeId = $examTypes->get($examTypeName);

                    if (! $examTypeId) {
                        continue;
                    }

                    // Existing exams for this type/class/year, oldest first, matched to
                    // $dateRanges by position — not by start_date value, since
                    // seedExamSchedule() below may move start_date away from the
                    // configured anchor (weekend skip, or extra days for many subjects).
                    $existingExams = Exam::where('exam_type_id', $examTypeId)
                        ->where('class_id', $class->id)
                        ->where('session_year', $sessionYear)
                        ->orderBy('start_date')
                        ->get();

                    foreach ($dateRanges as $index => $dateRange) {
                        $isPublished = $halfYearlyStart !== null && $dateRange['start'] <= $halfYearlyStart;

                        $exam = $existingExams->get($index)
                            ?? Exam::create([
                                'exam_type_id' => $examTypeId,
                                'class_id' => $class->id,
                                'session_year' => $sessionYear,
                                'start_date' => $dateRange['start'],
                                'end_date' => $dateRange['end'],
                                'is_published' => $isPublished,
                            ]);

                        if ($exam->is_published !== $isPublished) {
                            $exam->update(['is_published' => $isPublished]);
                        }

                        foreach ($subjects as $subject) {
                            $this->seedSubjectConfig($exam, $subject, $examTypeName);
                        }

                        $this->seedExamSchedule($exam, $subjects, Carbon::parse($dateRange['start']), $calculator);
                    }
                }
            }
        });
    }

    /**
     * Assigns each subject its own exam date, walking forward one working day
     * (per WorkingDaysCalculator — Friday/Saturday and public holidays) at a
     * time from $preferredStart, then sets the exam's start_date/end_date to
     * the actual first/last assigned date. Idempotent: a subject that
     * already has a schedule keeps its date; a newly-added subject is
     * appended right after the latest existing date, extending the exam
     * period rather than dropping it.
     *
     * @param  Collection<int, Subject>  $subjects
     */
    private function seedExamSchedule(Exam $exam, Collection $subjects, Carbon $preferredStart, WorkingDaysCalculator $calculator): void
    {
        $existingSchedules = ExamSchedule::where('exam_id', $exam->id)->get()->keyBy('subject_id');

        $cursor = $existingSchedules->isNotEmpty()
            ? Carbon::parse($existingSchedules->max('exam_date'))->addDay()
            : $preferredStart->copy();

        foreach ($subjects as $subject) {
            if ($existingSchedules->has($subject->id)) {
                continue;
            }

            while (! $calculator->isWorkingDay($cursor)) {
                $cursor->addDay();
            }

            ExamSchedule::create([
                'exam_id' => $exam->id,
                'subject_id' => $subject->id,
                'exam_date' => $cursor->toDateString(),
            ]);

            $cursor->addDay();
        }

        $allDates = ExamSchedule::where('exam_id', $exam->id)->pluck('exam_date');

        if ($allDates->isNotEmpty()) {
            $exam->update([
                'start_date' => $allDates->min(),
                'end_date' => $allDates->max(),
            ]);
        }
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
