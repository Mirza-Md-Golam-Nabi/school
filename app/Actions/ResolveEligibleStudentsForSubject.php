<?php

namespace App\Actions;

use App\Enums\SubjectType;
use App\Models\ClassGroupSubject;
use App\Models\StudentOptionalSubject;
use App\Models\StudentProfile;
use Illuminate\Support\Collection;

/**
 * Resolves which students in a class actually take a given subject, so a
 * group-restricted subject (e.g. Physics, only offered to the Science group)
 * or an optional subject only lists the students who are actually eligible
 * for it on the marks-entry sheet, instead of every active student in the
 * class.
 */
class ResolveEligibleStudentsForSubject
{
    /**
     * @return Collection<int, StudentProfile>
     */
    public function execute(int $classId, int $subjectId): Collection
    {
        $classSubjects = ClassGroupSubject::where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->get();

        if ($classSubjects->isEmpty()) {
            return collect();
        }

        $optionalSelectionStudentIds = StudentOptionalSubject::where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->pluck('student_id')
            ->flip();

        return StudentProfile::with('user')
            ->where('current_class_id', $classId)
            ->active()
            ->orderBy('roll_no')
            ->get()
            ->filter(function (StudentProfile $student) use ($classSubjects, $optionalSelectionStudentIds) {
                $groupId = $student->current_group_id ? (int) $student->current_group_id : null;

                // A group-specific row takes precedence over a "for all groups" (group_id
                // = null) row for the same subject, mirroring ResolveIsExtraOptionalSubject.
                $classSubject = $classSubjects->first(fn (ClassGroupSubject $r) => $r->group_id !== null && $r->group_id === $groupId)
                    ?? $classSubjects->first(fn (ClassGroupSubject $r) => $r->group_id === null);

                if (! $classSubject) {
                    return false;
                }

                if ($classSubject->subject_type === SubjectType::Compulsory) {
                    return true;
                }

                return $optionalSelectionStudentIds->has($student->id);
            })
            ->values();
    }
}
