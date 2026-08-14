<?php

namespace App\Filament\Teacher\Concerns;

trait ScopesToClassTeacherStudents
{
    /**
     * Class IDs where the logged-in teacher is the assigned class teacher.
     *
     * @return array<int>
     */
    protected static function currentTeacherClassIds(): array
    {
        return auth()->user()?->teacherProfile
            ?->classesAsClassTeacher()
            ->pluck('id')
            ->toArray() ?? [];
    }
}
