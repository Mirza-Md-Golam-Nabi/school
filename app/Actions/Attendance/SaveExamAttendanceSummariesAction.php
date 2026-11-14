<?php

namespace App\Actions\Attendance;

use App\Models\Exam;
use App\Models\StudentAttendanceSummary;
use App\Models\StudentProfile;
use Illuminate\Support\Facades\Validator;

/**
 * Saves the total attendance the class teacher / admin wrote for each student
 * of an exam's class. A blank present-days box removes that student's written
 * total, so they fall back to the daily attendance count again.
 */
class SaveExamAttendanceSummariesAction
{
    /**
     * @param  array<int|string, int|string|null>  $presentDays  present days keyed by student id
     * @return int how many students now have a written total
     */
    public function handle(Exam $exam, int|string|null $workingDays, array $presentDays, ?int $enteredBy): int
    {
        $presentDays = collect($presentDays)
            ->mapWithKeys(fn (int|string|null $days, int|string $studentId): array => [(int) $studentId => blank($days) ? null : $days])
            ->only($this->allowedStudentIds($exam))
            ->all();

        $this->validate($workingDays, $presentDays);

        $workingDays = (int) $workingDays;

        $existing = StudentAttendanceSummary::where('exam_id', $exam->id)
            ->get()
            ->keyBy('student_id');

        $saved = 0;

        foreach ($presentDays as $studentId => $days) {
            $summary = $existing->get($studentId);

            if ($days === null) {
                $summary?->delete();

                continue;
            }

            ($summary ?? new StudentAttendanceSummary([
                'exam_id' => $exam->id,
                'student_id' => $studentId,
            ]))->fill([
                'class_id' => $exam->class_id,
                'working_days' => $workingDays,
                'present_days' => (int) $days,
                'entered_by' => $enteredBy,
            ])->save();

            $saved++;
        }

        return $saved;
    }

    /**
     * Removes every written total of the exam, so all its students go back to
     * the daily attendance count.
     */
    public function clear(Exam $exam): int
    {
        $count = 0;

        // One by one so each removal reaches the activity log.
        StudentAttendanceSummary::where('exam_id', $exam->id)->get()->each(function (StudentAttendanceSummary $summary) use (&$count): void {
            $summary->delete();
            $count++;
        });

        return $count;
    }

    /**
     * Students of the exam's class, plus anyone who already has a written total
     * for the exam (e.g. a student who has since left the class).
     *
     * @return list<int>
     */
    private function allowedStudentIds(Exam $exam): array
    {
        return StudentProfile::withTrashed()
            ->where('current_class_id', $exam->class_id)
            ->pluck('id')
            ->merge(StudentAttendanceSummary::where('exam_id', $exam->id)->pluck('student_id'))
            ->map(fn (int|string $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int|string|null>  $presentDays
     */
    private function validate(int|string|null $workingDays, array $presentDays): void
    {
        $hasAnyPresentDays = collect($presentDays)->contains(fn (int|string|null $days): bool => $days !== null);

        Validator::make(
            ['workingDays' => $workingDays, 'presentDays' => $presentDays],
            [
                'workingDays' => [$hasAnyPresentDays ? 'required' : 'nullable', 'integer', 'min:1', 'max:366'],
                'presentDays.*' => ['nullable', 'integer', 'min:0', 'lte:workingDays'],
            ],
            [
                'presentDays.*.lte' => 'উপস্থিত দিন মোট কার্যদিবসের চেয়ে বেশি হতে পারবে না।',
            ],
            [
                'workingDays' => 'মোট কার্যদিবস',
                'presentDays.*' => 'উপস্থিত দিন',
            ],
        )->validate();
    }
}
