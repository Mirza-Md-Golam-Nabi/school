<?php

namespace App\Filament\Concerns;

use App\Actions\Attendance\SaveClassAttendanceAction;
use App\Enums\AttendanceStatus;
use App\Enums\StudentStatus;
use App\Models\Attendance;
use App\Models\Classes;
use App\Models\StudentProfile;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

trait ManagesClassAttendance
{
    #[Url(as: 'classId')]
    public int $classId = 0;

    #[Url(as: 'date')]
    public string $date = '';

    /** @var array<string> */
    public array $presentIds = [];

    public function mount(): void
    {
        if (blank($this->date)) {
            $this->date = now()->toDateString();
        }

        $this->loadExistingAttendance();
    }

    public function updatedDate(): void
    {
        $this->loadExistingAttendance();
    }

    public function selectAll(): void
    {
        $this->presentIds = $this->getStudents()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
    }

    public function deselectAll(): void
    {
        $this->presentIds = [];
    }

    public function save(): void
    {
        $students = $this->getStudents();

        $count = app(SaveClassAttendanceAction::class)->handle(
            $this->classId,
            $this->resolveClass(),
            $this->date,
            $students,
            $this->presentIds,
            auth()->id(),
            auth()->user()?->name ?? 'Unknown',
        );

        Notification::make()
            ->success()
            ->title('Attendance saved')
            ->body('Saved for '.$count.' students — '.$this->date)
            ->send();
    }

    public function getStudents(): Collection
    {
        $idsWithAttendance = Attendance::where('attendable_type', StudentProfile::class)
            ->where('date', $this->date)
            ->where('class_id', $this->classId)
            ->pluck('attendable_id');

        return StudentProfile::with('user')
            ->withTrashed()
            ->where('current_class_id', $this->classId)
            ->where(function ($q) use ($idsWithAttendance) {
                $q->where(fn ($q2) => $q2->where('status', StudentStatus::Active)->whereNull('deleted_at'))
                    ->orWhereIn('id', $idsWithAttendance);
            })
            ->orderBy('roll_no')
            ->get();
    }

    public function getYesterdayAttendance(): Collection
    {
        $yesterday = Carbon::parse($this->date)->subDay()->toDateString();
        $studentIds = $this->getStudents()->pluck('id');

        return Attendance::query()
            ->where('attendable_type', StudentProfile::class)
            ->whereIn('attendable_id', $studentIds)
            ->where('date', $yesterday)
            ->where('class_id', $this->classId)
            ->whereNull('subject_id')
            ->pluck('status', 'attendable_id');
    }

    private function loadExistingAttendance(): void
    {
        $studentIds = $this->getStudents()->pluck('id')->toArray();

        $this->presentIds = Attendance::query()
            ->where('attendable_type', StudentProfile::class)
            ->whereIn('attendable_id', $studentIds)
            ->where('date', $this->date)
            ->where('class_id', $this->classId)
            ->where('status', AttendanceStatus::Present)
            ->pluck('attendable_id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
    }

    private function resolveClass(): ?Classes
    {
        return $this->classId ? Classes::find($this->classId) : null;
    }
}
