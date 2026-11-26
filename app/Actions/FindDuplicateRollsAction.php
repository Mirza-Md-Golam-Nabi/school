<?php

namespace App\Actions;

use App\Models\StudentProfile;
use Illuminate\Support\Collection;

/**
 * Finds active students who share a roll with one of the given students in
 * the same class, section and session — e.g. a promoted student who kept
 * their roll and a repeating student with the same roll. A shared roll is
 * allowed (it only warns), since the system tells students apart by id.
 */
class FindDuplicateRollsAction
{
    /**
     * One line per shared roll, e.g. "Class 12 — Roll 15: Rahim, Karim".
     *
     * @param  array<int, int>  $studentIds
     * @return Collection<int, string>
     */
    public function handle(array $studentIds): Collection
    {
        $students = StudentProfile::query()
            ->whereKey($studentIds)
            ->active()
            ->whereNotNull('roll_no')
            ->get(['id', 'current_class_id', 'current_section_id', 'session_year', 'roll_no']);

        if ($students->isEmpty()) {
            return collect();
        }

        $rollKey = fn (StudentProfile $student): string => implode('|', [
            $student->current_class_id,
            $student->current_section_id,
            $student->session_year,
            $student->roll_no,
        ]);
        $checkedKeys = $students->map($rollKey)->unique();

        return StudentProfile::query()
            ->with(['user:id,name', 'class:id,name'])
            ->active()
            ->whereIn('current_class_id', $students->pluck('current_class_id')->unique())
            ->whereIn('session_year', $students->pluck('session_year')->unique())
            ->whereIn('roll_no', $students->pluck('roll_no')->unique())
            ->orderBy('id')
            ->get()
            ->groupBy($rollKey)
            ->filter(fn (Collection $sameRoll, string $key): bool => $sameRoll->count() > 1 && $checkedKeys->contains($key))
            ->map(function (Collection $sameRoll): string {
                $first = $sameRoll->first();
                $names = $sameRoll->map(fn (StudentProfile $student): string => $student->user?->name ?? "#{$student->id}")->implode(', ');

                return "{$first->class?->name} — Roll {$first->roll_no}: {$names}";
            })
            ->values();
    }
}
