<?php

namespace App\Filament\Teacher\Pages;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\TeacherProfile;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

class MyAttendance extends Page
{
    protected string $view = 'filament.teacher.pages.my-attendance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?string $navigationLabel = 'My Attendance';

    protected static ?int $navigationSort = 2;

    public ?string $todayStatus = null;

    public function mount(): void
    {
        $this->loadTodayStatus();
    }

    private function loadTodayStatus(): void
    {
        $profile = $this->resolveProfile();

        if (! $profile) {
            return;
        }

        $this->todayStatus = Attendance::query()
            ->where('attendable_type', TeacherProfile::class)
            ->where('attendable_id', $profile->id)
            ->where('date', today())
            ->whereNull('class_id')
            ->whereNull('subject_id')
            ->value('status')?->value;
    }

    public function markAs(string $status): void
    {
        $profile = $this->resolveProfile();

        if (! $profile) {
            return;
        }

        $isPresentLike = in_array($status, ['present', 'late'], true);

        Attendance::updateOrCreate(
            [
                'attendable_type' => TeacherProfile::class,
                'attendable_id' => $profile->id,
                'date' => today(),
                'class_id' => null,
                'subject_id' => null,
            ],
            [
                'status' => $status,
                'source' => AttendanceSource::Manual,
                'marked_by' => auth()->id(),
                'entry_time' => $isPresentLike ? now()->format('H:i:s') : null,
            ]
        );

        $this->todayStatus = $status;

        Notification::make()
            ->success()
            ->title('Attendance marked as '.AttendanceStatus::from($status)->getLabel())
            ->send();
    }

    public function getViewData(): array
    {
        $profile = $this->resolveProfile();

        if (! $profile) {
            return [
                'profile' => null,
                'statusConfig' => [],
                'buttons' => [],
                'greeting' => '',
                'current' => null,
                'history' => collect(),
            ];
        }

        $statusConfig = [
            'present' => [
                'label' => 'Present',
                'color' => 'success',
                'icon' => 'heroicon-s-check-circle',
                'circleBg' => 'bg-success-100 dark:bg-success-900',
                'iconClass' => 'h-12 w-12 text-success-500',
                'textClass' => 'mt-3 text-2xl font-bold tracking-tight text-success-600 dark:text-success-400',
                'btnSelected' => 'bg-success-50 text-success-600 dark:bg-success-950 dark:text-success-400',
                'btnUnselected' => 'text-gray-400 hover:bg-success-50 hover:text-success-600 dark:text-gray-500 dark:hover:bg-success-950 dark:hover:text-success-400',
                'dotClass' => 'h-1.5 w-1.5 rounded-full bg-success-500',
            ],
            'late' => [
                'label' => 'Late',
                'color' => 'warning',
                'icon' => 'heroicon-s-clock',
                'circleBg' => 'bg-warning-100 dark:bg-warning-900',
                'iconClass' => 'h-12 w-12 text-warning-500',
                'textClass' => 'mt-3 text-2xl font-bold tracking-tight text-warning-600 dark:text-warning-400',
                'btnSelected' => 'bg-warning-50 text-warning-600 dark:bg-warning-950 dark:text-warning-400',
                'btnUnselected' => 'text-gray-400 hover:bg-warning-50 hover:text-warning-600 dark:text-gray-500 dark:hover:bg-warning-950 dark:hover:text-warning-400',
                'dotClass' => 'h-1.5 w-1.5 rounded-full bg-warning-500',
            ],
            'leave' => [
                'label' => 'Leave',
                'color' => 'info',
                'icon' => 'heroicon-s-calendar-days',
                'circleBg' => 'bg-info-100 dark:bg-info-900',
                'iconClass' => 'h-12 w-12 text-info-500',
                'textClass' => 'mt-3 text-2xl font-bold tracking-tight text-info-600 dark:text-info-400',
                'btnSelected' => 'bg-info-50 text-info-600 dark:bg-info-950 dark:text-info-400',
                'btnUnselected' => 'text-gray-400 hover:bg-info-50 hover:text-info-600 dark:text-gray-500 dark:hover:bg-info-950 dark:hover:text-info-400',
                'dotClass' => 'h-1.5 w-1.5 rounded-full bg-info-500',
            ],
            'absent' => [
                'label' => 'Absent',
                'color' => 'danger',
                'icon' => 'heroicon-s-x-circle',
                'circleBg' => 'bg-danger-100 dark:bg-danger-900',
                'iconClass' => 'h-12 w-12 text-danger-500',
                'textClass' => 'mt-3 text-2xl font-bold tracking-tight text-danger-600 dark:text-danger-400',
                'btnSelected' => 'bg-danger-50 text-danger-600 dark:bg-danger-950 dark:text-danger-400',
                'btnUnselected' => 'text-gray-400 hover:bg-danger-50 hover:text-danger-600 dark:text-gray-500 dark:hover:bg-danger-950 dark:hover:text-danger-400',
                'dotClass' => 'h-1.5 w-1.5 rounded-full bg-danger-500',
            ],
        ];

        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
        $current = $this->todayStatus ? $statusConfig[$this->todayStatus] : null;

        $buttons = array_map(function (string $value, array $cfg): array {
            $isSelected = $this->todayStatus === $value;

            return [
                'value' => $value,
                'cfg' => $cfg,
                'btnClass' => $isSelected ? $cfg['btnSelected'] : $cfg['btnUnselected'],
                'dotClass' => $isSelected ? $cfg['dotClass'] : 'h-1.5 w-1.5 rounded-full bg-transparent',
            ];
        }, array_keys($statusConfig), array_values($statusConfig));

        $history = $this->getRecentHistory($profile->id);

        return compact('profile', 'statusConfig', 'buttons', 'greeting', 'current', 'history');
    }

    private function getRecentHistory(int $profileId): Collection
    {
        return Attendance::query()
            ->where('attendable_type', TeacherProfile::class)
            ->where('attendable_id', $profileId)
            ->whereNull('class_id')
            ->whereNull('subject_id')
            ->where('date', '!=', today())
            ->orderByDesc('date')
            ->limit(10)
            ->get();
    }

    private function resolveProfile(): ?TeacherProfile
    {
        return auth()->user()?->teacherProfile;
    }
}
