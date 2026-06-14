<?php

namespace Database\Seeders;

use App\Enums\SubjectType;
use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class ClassSubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjectNames = collect(SubjectSeeder::subjects())->pluck('name');
        $subjects = Subject::whereIn('name', $subjectNames)->get();
        $classes = Classes::orderBy('order')->get();

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
