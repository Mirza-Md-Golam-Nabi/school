<?php

namespace App\Actions;

use App\Enums\OptionalSubjectRole;
use App\Enums\SubjectType;
use App\Models\StudentProfile;
use Illuminate\Support\Collection;

class BuildStudentSubjectListAction
{
    /**
     * ছাত্রের প্রোফাইল পেজের বিষয় তালিকা: আবশ্যিক, group-এর বিষয় ও অতিরিক্ত বিষয়।
     *
     * @return list<array{heading: string, subjects: Collection<int, string>, empty?: string, color: string, hideWhenEmpty: bool}>
     */
    public function handle(StudentProfile $student): array
    {
        // subjectsForGroup() returns both the "every student" rows (group_id null)
        // and this student's own group's rows in one collection — group_id is what
        // tells them apart, not subject_type (a group's own compulsory subjects,
        // e.g. Humanities' History/Civics/Geography, are still group-specific).
        $classGroupSubjects = $student->class?->subjectsForGroup($student->current_group_id) ?? collect();

        $compulsorySubjects = $classGroupSubjects
            ->filter(fn ($classGroupSubject) => $classGroupSubject->group_id === null && $classGroupSubject->subject_type === SubjectType::Compulsory)
            ->pluck('subject.display_name')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $groupCompulsorySubjects = $classGroupSubjects
            ->filter(fn ($classGroupSubject) => $classGroupSubject->group_id !== null && $classGroupSubject->subject_type === SubjectType::Compulsory)
            ->pluck('subject.display_name')
            ->filter();

        $optionalSelections = $student->optionalSubjects()->with('subject')->get();

        $mainOptionalSubjects = $optionalSelections
            ->where('role', OptionalSubjectRole::MainOptional)
            ->pluck('subject.display_name')
            ->filter();

        return [
            [
                'heading' => 'Compulsory Subjects',
                'subjects' => $compulsorySubjects,
                'empty' => 'No compulsory subjects found.',
                'color' => 'info',
                'hideWhenEmpty' => false,
            ],
            [
                'heading' => 'Group Subject',
                'subjects' => $groupCompulsorySubjects->merge($mainOptionalSubjects)->unique()->sort()->values(),
                'color' => 'warning',
                'hideWhenEmpty' => true,
            ],
            [
                'heading' => 'Additional Subject',
                'subjects' => $optionalSelections
                    ->where('role', OptionalSubjectRole::ExtraOptional)
                    ->pluck('subject.display_name')
                    ->filter()
                    ->values(),
                'color' => 'success',
                'hideWhenEmpty' => true,
            ],
        ];
    }
}
