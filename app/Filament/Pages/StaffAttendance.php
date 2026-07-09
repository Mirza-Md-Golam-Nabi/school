<?php

namespace App\Filament\Pages;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\EmploymentStatus;
use App\Models\Attendance;
use App\Models\StaffProfile;
use App\Models\User;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

class StaffAttendance extends Page
{
    protected string $view = 'filament.pages.staff-attendance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?string $navigationLabel = 'Staff Attendance';

    protected static ?int $navigationSort = 3;

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
        $staffIds = $this->getStaff()->pluck('id')->toArray();

        $this->presentIds = Attendance::query()
            ->where('attendable_type', StaffProfile::class)
            ->whereIn('attendable_id', $staffIds)
            ->where('date', $this->date)
            ->where('status', AttendanceStatus::Present)
            ->pluck('attendable_id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
    }

    public function selectAll(): void
    {
        $this->presentIds = $this->getStaff()
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
        $staff = $this->getStaff();

        foreach ($staff as $member) {
            $isPresent = in_array((string) $member->id, $this->presentIds);

            Attendance::updateOrCreate(
                [
                    'attendable_type' => StaffProfile::class,
                    'attendable_id' => $member->id,
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
            ->body('Saved for '.$staff->count().' staff — '.$this->date)
            ->send();
    }

    private function sendBackdatingNotification(): void
    {
        $marker = auth()->user()?->name ?? 'Unknown';
        $type = $this->date < now()->toDateString() ? 'past' : 'future';

        $body = "{$marker} marked {$type} staff attendance on {$this->date}.";

        Notification::make()
            ->warning()
            ->title('Backdated Attendance Marked')
            ->body($body)
            ->sendToDatabase(
                User::role(['admin', 'super-admin'])->get()
            );
    }

    public function getStaff(): Collection
    {
        $idsWithAttendance = Attendance::where('attendable_type', StaffProfile::class)
            ->where('date', $this->date)
            ->pluck('attendable_id');

        return StaffProfile::with('user')
            ->withTrashed()
            ->where(function ($q) use ($idsWithAttendance) {
                $q->where(fn ($q2) => $q2->where('status', EmploymentStatus::Active)->whereNull('deleted_at'))
                    ->orWhereIn('id', $idsWithAttendance);
            })
            ->orderBy('id')
            ->get();
    }
}
