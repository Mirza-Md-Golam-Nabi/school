<?php

namespace App\Actions;

use App\Enums\OptionalSubjectRole;
use App\Enums\PromotionStatus;
use App\Enums\StudentStatus;
use App\Models\StudentClassHistory;
use App\Models\StudentOptionalSubject;
use App\Models\StudentProfile;
use Illuminate\Support\Facades\DB;

class PromoteStudentsAction
{
    /**
     * @param  array<int, array<string, mixed>>  $promotions  keyed by student_id => ['status', 'class_id', 'section_id', 'group_id', 'roll_no', 'remarks', 'main_optional_subject_id', 'extra_optional_subject_id']
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

                    $this->saveOptionalSubjects(
                        $student,
                        $data['class_id'] ? (int) $data['class_id'] : null,
                        $data['group_id'] ? (int) $data['group_id'] : null,
                        $data['main_optional_subject_id'] ?? null,
                        $data['extra_optional_subject_id'] ?? null,
                    );
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

    /**
     * নতুন (target) ক্লাসের জন্য main/extra optional subject সেভ করে — আগের
     * ক্লাসের selection ইতিহাস হিসেবে অক্ষত থাকে, মোছা হয় না। Group ছাড়া
     * promotion হলে (যেমন non-group ক্লাসে) কিছু সেভ হয় না।
     */
    private function saveOptionalSubjects(
        StudentProfile $student,
        ?int $classId,
        ?int $groupId,
        ?int $mainOptionalSubjectId,
        ?int $extraOptionalSubjectId,
    ): void {
        if (! $classId || ! $groupId) {
            return;
        }

        $roleSubjects = [
            OptionalSubjectRole::MainOptional->value => $mainOptionalSubjectId,
            OptionalSubjectRole::ExtraOptional->value => $extraOptionalSubjectId,
        ];

        foreach ($roleSubjects as $role => $subjectId) {
            if (! $subjectId) {
                StudentOptionalSubject::where('student_id', $student->id)
                    ->where('class_id', $classId)
                    ->where('role', $role)
                    ->delete();

                continue;
            }

            StudentOptionalSubject::updateOrCreate(
                ['student_id' => $student->id, 'class_id' => $classId, 'role' => $role],
                ['group_id' => $groupId, 'subject_id' => $subjectId]
            );
        }
    }
}
