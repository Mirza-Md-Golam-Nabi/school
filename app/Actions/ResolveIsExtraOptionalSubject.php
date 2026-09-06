<?php

namespace App\Actions;

use App\Enums\OptionalSubjectRole;
use App\Enums\SubjectType;
use App\Models\ClassGroupSubject;
use App\Models\StudentOptionalSubject;
use Illuminate\Support\Collection;

class ResolveIsExtraOptionalSubject
{
    /**
     * A subject only behaves as "extra optional" (bonus marks, excluded from GPA
     * on failure) when two things line up: the class/group curriculum lists it as
     * an optional subject (not compulsory), AND this specific student chose it as
     * their extra_optional pick (as opposed to main_optional, which behaves like
     * a compulsory subject for GPA purposes). Compulsory subjects, and optional
     * subjects the student hasn't recorded a choice for, are never extra optional.
     *
     * @param  Collection<int, Collection<int, ClassGroupSubject>>  $subjectTypeRecords  subject_id → ClassGroupSubject records for the class
     * @param  ?Collection<int, StudentOptionalSubject>  $studentOptionalSelections  this student's optional-subject choices for the class
     */
    public function execute(
        Collection $subjectTypeRecords,
        ?Collection $studentOptionalSelections,
        int $subjectId,
        ?int $groupId
    ): bool {
        $records = $subjectTypeRecords->get($subjectId);

        $classSubject = $records
            ? ($records->first(fn (ClassGroupSubject $r) => $r->group_id !== null && $r->group_id === $groupId)
                ?? $records->first(fn (ClassGroupSubject $r) => $r->group_id === null))
            : null;

        if (! $classSubject || $classSubject->subject_type !== SubjectType::Optional) {
            return false;
        }

        $selection = $studentOptionalSelections?->firstWhere('subject_id', $subjectId);

        return $selection?->role === OptionalSubjectRole::ExtraOptional;
    }
}
