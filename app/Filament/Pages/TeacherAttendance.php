<?php

namespace App\Filament\Pages;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Permissions\AttendancePermission;
use App\Filament\Concerns\HasAttendancePagePermission;
use App\Models\Attendance;
use App\Models\TeacherProfile;
use App\Models\User;
use BackedEnum;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

class TeacherAttendance extends Page
{
    use HasAttendancePagePermission;

    protected string $view = 'filament.pages.teacher-attendance';

    protected static function attendancePermission(): AttendancePermission
    {
        return AttendancePermission::MARK_ATTENDANCE;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?string $navigationLabel = 'Teacher Attendance';

    protected static ?int $navigationSort = 2;

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

    private function loadExistingAttendance(): void
    {
        $teacherIds = $this->getTeachers()->pluck('id')->toArray();

        $this->presentIds = Attendance::query()
            ->where('attendable_type', TeacherProfile::class)
            ->whereIn('attendable_id', $teacherIds)
            ->where('date', $this->date)
            ->where('status', AttendanceStatus::Present)
            ->pluck('attendable_id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
    }

    public function selectAll(): void
    {
        $this->presentIds = $this->getTeachers()
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
        $teachers = $this->getTeachers();

        foreach ($teachers as $teacher) {
            $isPresent = in_array((string) $teacher->id, $this->presentIds);

            Attendance::updateOrCreate(
                [
                    'attendable_type' => TeacherProfile::class,
                    'attendable_id' => $teacher->id,
                    'date' => $this->date,
                    'class_id' => null,
                    'subject_id' => null,
                ],
                [
                    'status' => $isPresent ? AttendanceStatus::Present : AttendanceStatus::Absent,
                    'source' => AttendanceSource::Manual,
                    'marked_by' => $markedBy,
                    'entry_time' => $isPresent ? now()->format('H:i:s') : null,
                ]
            );
        }

        if ($this->date !== now()->toDateString()) {
            $this->sendBackdatingNotification();
        }

        Notification::make()
            ->success()
            ->title('Attendance saved')
            ->body('Saved for '.$teachers->count().' teachers — '.$this->date)
            ->send();
    }

    private function sendBackdatingNotification(): void
    {
        $marker = auth()->user()?->name ?? 'Unknown';
        $type = $this->date < now()->toDateString() ? 'past' : 'future';

        $body = "{$marker} marked {$type} teacher attendance on {$this->date}.";

        Notification::make()
            ->warning()
            ->title('Backdated Attendance Marked')
            ->body($body)
            ->sendToDatabase(
                User::role(['admin', 'super-admin'])->get()
            );
    }

    public function getTeachers(): Collection
    {
        return TeacherProfile::with('user')
            ->where('status', EmploymentStatus::Active)
            ->get()
            ->sortBy('user.name')
            ->values();
    }

    public function getYesterdayAttendance(): Collection
    {
        $yesterday = Carbon::parse($this->date)->subDay()->toDateString();
        $teacherIds = $this->getTeachers()->pluck('id');

        return Attendance::query()
            ->where('attendable_type', TeacherProfile::class)
            ->whereIn('attendable_id', $teacherIds)
            ->where('date', $yesterday)
            ->whereNull('class_id')
            ->whereNull('subject_id')
            ->pluck('status', 'attendable_id');
    }
}
