<?php

namespace App\Actions;

use App\Enums\PromotionStatus;
use App\Enums\StudentStatus;
use App\Models\StudentClassHistory;
use App\Models\StudentProfile;
use Illuminate\Support\Facades\DB;

class PromoteStudentsAction
{
    /**
     * @param  array<int, array<string, mixed>>  $promotions  keyed by student_id => ['status', 'class_id', 'section_id', 'group_id', 'roll_no', 'remarks']
     */
    public function handle(array $promotions, ?int $promotedBy): int
    {
        $students = StudentProfile::whereIn('id', array_map('intval', array_keys($promotions)))
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($promotions, $students, $promotedBy): void {
            foreach ($promotions as $studentId => $data) {
                $student = $students->get((int) $studentId);

                if (! $student) {
                    continue;
                }

                $status = $data['status'] instanceof PromotionStatus
                    ? $data['status']
                    : PromotionStatus::from($data['status']);

                StudentClassHistory::create([
                    'student_id' => $student->id,
                    'class_id' => $student->current_class_id,
                    'section_id' => $student->current_section_id,
                    'group_id' => $student->current_group_id,
                    'roll_no' => $student->roll_no,
                    'session_year' => $student->session_year,
                    'status' => $status,
                    'promoted_by' => $promotedBy,
                    'remarks' => $data['remarks'] ?: null,
                ]);

                if (in_array($status, [PromotionStatus::Promoted, PromotionStatus::Repeated], true)) {
                    $student->update([
                        'current_class_id' => $data['class_id'],
                        'current_section_id' => $data['section_id'] ?: null,
                        'current_group_id' => $data['group_id'] ?: null,
                        'roll_no' => $data['roll_no'],
                        'session_year' => $student->session_year + 1,
                    ]);
                } else {
                    $student->update([
                        'status' => match ($status) {
                            PromotionStatus::Transferred => StudentStatus::Transferred,
                            PromotionStatus::Graduated => StudentStatus::Graduated,
                            default => StudentStatus::Dropped,
                        },
                    ]);
                }
            }
        });

        return count($promotions);
    }
}
