<?php

namespace App\Filament\Pages;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\StudentStatus;
use App\Models\Attendance;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

class MarkStudentAttendance extends Page
{
    protected string $view = 'filament.pages.mark-student-attendance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static bool $shouldRegisterNavigation = false;

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

    public function getTitle(): string|Htmlable
    {
        return 'Mark Attendance — '.($this->resolveClass()?->name ?? 'Class');
    }

    public function getBreadcrumbs(): array
    {
        return [
            route('filament.admin.pages.student-attendance') => 'Student Attendance',
            '' => $this->resolveClass()?->name ?? 'Class',
        ];
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
        $markedBy = auth()->id();
        $students = $this->getStudents();

        foreach ($students as $student) {
            $isPresent = in_array((string) $student->id, $this->presentIds);

            Attendance::updateOrCreate(
                [
                    'attendable_type' => StudentProfile::class,
                    'attendable_id' => $student->id,
                    'date' => $this->date,
                    'class_id' => $this->classId,
                    'subject_id' => null,
                ],
                [
                    'status' => $isPresent ? AttendanceStatus::Present : AttendanceStatus::Absent,
                    'source' => AttendanceSource::Manual,
                    'marked_by' => $markedBy,
                ]
            );
        }

        if ($this->date !== now()->toDateString()) {
            $this->sendBackdatingNotification();
        }

        Notification::make()
            ->success()
            ->title('Attendance saved')
            ->body('Saved for '.$students->count().' students — '.$this->date)
            ->send();
    }

    private function sendBackdatingNotification(): void
    {
        $class = $this->resolveClass();
        $marker = auth()->user()?->name ?? 'Unknown';
        $type = $this->date < now()->toDateString() ? 'past' : 'future';

        $body = "{$marker} marked {$type} attendance for {$class?->name} on {$this->date}.";

        Notification::make()
            ->warning()
            ->title('Backdated Attendance Marked')
            ->body($body)
            ->sendToDatabase(
                User::role(['admin', 'super-admin'])->get()
            );
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

    private function resolveClass(): ?Classes
    {
        return $this->classId ? Classes::find($this->classId) : null;
    }
}
