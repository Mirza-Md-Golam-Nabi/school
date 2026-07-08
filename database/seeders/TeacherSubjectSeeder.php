<?php

namespace Database\Seeders;

use App\Models\Classes;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\TeacherSubject;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class TeacherSubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sessionYear = (int) now()->format('Y');
        $teachers = TeacherProfile::all();
        $subjects = Subject::all();
        $classes = Classes::with('sections')->active()->orderBy('order')->get();

        foreach ($classes as $class) {
            $sections = $class->sections->isNotEmpty() ? $class->sections : collect([null]);

            foreach ($subjects as $subject) {
                foreach ($sections as $section) {
                    $this->assignTeacher($teachers, $subject->id, $class->id, $section?->id, $sessionYear);
                }
            }
        }
    }

    /**
     * @param  Collection<int, TeacherProfile>  $teachers
     */
    private function assignTeacher(
        Collection $teachers,
        int $subjectId,
        int $classId,
        ?int $sectionId,
        int $sessionYear
    ): void {
        $exists = TeacherSubject::where([
            'subject_id' => $subjectId,
            'class_id' => $classId,
            'section_id' => $sectionId,
            'session_year' => $sessionYear,
        ])->exists();

        if ($exists) {
            return;
        }

        TeacherSubject::create([
            'teacher_id' => $teachers->random()->id,
            'subject_id' => $subjectId,
            'class_id' => $classId,
            'section_id' => $sectionId,
            'session_year' => $sessionYear,
        ]);
    }
}
