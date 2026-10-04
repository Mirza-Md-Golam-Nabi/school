<?php

namespace App\Notifications;

use App\Enums\PunchDirection;
use App\Filament\Student\Pages\MyAttendanceRanking;
use App\Models\Attendance;
use App\Notifications\Channels\PerDeviceWebPushChannel;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StudentDevicePunchNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Attendance $attendance,
        public readonly PunchDirection $direction,
    ) {}

    /**
     * @return array<int, class-string|string>
     */
    public function via(object $notifiable): array
    {
        return ['database', PerDeviceWebPushChannel::class];
    }

    /**
     * @return array{title: string, icon: string, body: string, data: array<string, mixed>}
     */
    public function toWebPushPayload(object $notifiable): array
    {
        return [
            'title' => $this->buildTitle(),
            'icon' => '/icons/192x192.png',
            'body' => $this->buildBody(),
            'data' => [
                'url' => MyAttendanceRanking::getUrl(panel: 'student'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->buildTitle())
            ->body($this->buildBody())
            ->color($this->attendance->status->getColor())
            ->icon($this->attendance->status->getIcon())
            ->getDatabaseMessage() + [
                'attendance_id' => $this->attendance->id,
                'direction' => $this->direction->value,
                'status' => $this->attendance->status->value,
                'date' => $this->attendance->date?->toDateString(),
            ];
    }

    private function buildTitle(): string
    {
        return match ($this->direction) {
            PunchDirection::Entry => 'Arrived at School',
            PunchDirection::Exit => 'Left School',
        };
    }

    private function buildBody(): string
    {
        $date = $this->attendance->date?->toDateString();

        return match ($this->direction) {
            PunchDirection::Entry => "Your entry was recorded at {$this->formatTime($this->attendance->entry_time)} on {$date} ({$this->attendance->status->getLabel()}).",
            PunchDirection::Exit => "Your exit was recorded at {$this->formatTime($this->attendance->exit_time)} on {$date}.",
        };
    }

    private function formatTime(?string $time): string
    {
        return $time === null ? '-' : substr($time, 0, 5);
    }
}
