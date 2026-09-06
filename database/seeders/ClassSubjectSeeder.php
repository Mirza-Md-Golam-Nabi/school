<?php

namespace Database\Seeders;

use App\Enums\ClassLevel;
use App\Enums\SubjectType;
use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Models\Subject;
use App\Support\SubjectDefinitions;
use Illuminate\Database\Seeder;

class ClassSubjectSeeder extends Seeder
{
    public function run(): void
    {
        $this->attachSubjectsToLevel(ClassLevel::Primary, SubjectDefinitions::primary());
        $this->attachSubjectsToLevel(ClassLevel::Secondary, SubjectDefinitions::secondary());
    }

    /**
     * Attach every subject in the given level's definition list, as
     * compulsory, to every active class of that level — primary subjects
     * only reach primary classes, secondary subjects only reach secondary
     * classes, so a Class 1 student never sees "Accounting" or "Physics".
     *
     * @param  array<int, array<string, mixed>>  $subjectDefinitions
     */
    private function attachSubjectsToLevel(ClassLevel $level, array $subjectDefinitions): void
    {
        $subjectNames = collect($subjectDefinitions)->pluck('name');
        $subjects = Subject::whereIn('name', $subjectNames)->get();
        $classes = Classes::active()->where('level', $level)->orderBy('order')->get();

        foreach ($classes as $class) {
            foreach ($subjects as $subject) {
                ClassGroupSubject::firstOrCreate(
                    [
                        'class_id' => $class->id,
                        'group_id' => null,
                        'subject_id' => $subject->id,
                    ],
                    ['subject_type' => SubjectType::Compulsory]
                );
            }
        }
    }
}
