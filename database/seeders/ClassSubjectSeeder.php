<?php

namespace Database\Seeders;

use App\Enums\ClassLevel;
use App\Enums\SubjectType;
use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Models\Group;
use App\Models\Subject;
use App\Support\ClassGroupSubjectDefinitions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class ClassSubjectSeeder extends Seeder
{
    public function run(): void
    {
        $this->attachMapping(
            ClassGroupSubjectDefinitions::primary(),
            Classes::where('level', ClassLevel::Primary)->get(),
            collect()
        );

        $this->attachMapping(
            ClassGroupSubjectDefinitions::classSixToEight(),
            Classes::where('level', ClassLevel::Secondary)->where('has_group', false)->get(),
            collect()
        );

        $groupedClasses = Classes::where('level', ClassLevel::Secondary)->where('has_group', true)->get();

        $this->attachMapping(
            ClassGroupSubjectDefinitions::secondary(),
            $groupedClasses,
            $this->resolveGroups()
        );
    }

    /**
     * Attach every subject in the given group-subject mapping — as
     * compulsory or optional, for the given group (or every group when
     * `group` is null) — to each of the given classes.
     *
     * @param  array<int, array{group: ?string, subject_type: SubjectType, subjects: array<int, string>}>  $mapping
     * @param  Collection<int, Classes>  $classes
     * @param  Collection<string, Group>  $groups
     */
    private function attachMapping(array $mapping, Collection $classes, Collection $groups): void
    {
        if ($classes->isEmpty()) {
            return;
        }

        foreach ($mapping as $entry) {
            $groupId = $entry['group'] === null ? null : $groups[$entry['group']]->id;
            $subjects = Subject::whereIn('name', $entry['subjects'])->get();

            foreach ($subjects as $subject) {
                foreach ($classes as $class) {
                    ClassGroupSubject::firstOrCreate(
                        [
                            'class_id' => $class->id,
                            'group_id' => $groupId,
                            'subject_id' => $subject->id,
                        ],
                        ['subject_type' => $entry['subject_type']]
                    );
                }
            }
        }
    }

    /**
     * @return Collection<string, Group>
     */
    private function resolveGroups(): Collection
    {
        return Group::whereIn('name', ClassGroupSubjectDefinitions::SECONDARY_GROUPS)->get()->keyBy('name');
    }
}
