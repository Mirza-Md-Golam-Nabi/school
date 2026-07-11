<?php

namespace App\Filament\Student\Pages;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\StudentProfile;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class MyAttendanceRanking extends Page
{
    protected string $view = 'filament.student.pages.my-attendance-ranking';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string|Htmlable
    {
        $className = $this->getStudentProfile()?->class?->name;

        return $className ? "{$className} — Attendance Ranking" : 'Attendance Ranking';
    }

    /**
     * @return Collection<int, array{id: int, name: string, roll_no: ?int, present_count: int}>
     */
    public function getStudents(): Collection
    {
        $profile = $this->getStudentProfile();

        if (! $profile || ! $profile->current_class_id) {
            return collect();
        }

        $year = (int) now()->year;
        $classId = $profile->current_class_id;

        $counts = Attendance::where('attendable_type', StudentProfile::class)
            ->where('status', AttendanceStatus::Present)
            ->where('class_id', $classId)
            ->whereYear('date', $year)
            ->selectRaw('attendable_id, count(*) as present_count')
            ->groupBy('attendable_id')
            ->get()
            ->keyBy('attendable_id');

        return StudentProfile::with('user')
            ->where('current_class_id', $classId)
            ->active()
            ->get()
            ->map(fn (StudentProfile $student) => [
                'id' => $student->id,
                'name' => $student->user?->name ?? '—',
                'roll_no' => $student->roll_no,
                'present_count' => $counts->get($student->id)?->present_count ?? 0,
            ])
            ->sortByDesc('present_count')
            ->values();
    }

    public function getMyStudentId(): ?int
    {
        return $this->getStudentProfile()?->id;
    }

    private function getStudentProfile(): ?StudentProfile
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->studentProfile;
    }
}
