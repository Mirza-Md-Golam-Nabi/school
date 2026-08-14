<?php

namespace App\Filament\Teacher\Concerns;

use App\Models\TeacherSubject;

trait ScopesToTaughtClasses
{
    /**
     * Class IDs where the logged-in teacher has ever been assigned to teach a subject.
     *
     * @return array<int>
     */
    protected static function currentTeacherTaughtClassIds(): array
    {
        $teacherId = auth()->user()?->teacherProfile?->id;

        if (! $teacherId) {
            return [];
        }

        return TeacherSubject::where('teacher_id', $teacherId)
            ->distinct()
            ->pluck('class_id')
            ->toArray();
    }
}
