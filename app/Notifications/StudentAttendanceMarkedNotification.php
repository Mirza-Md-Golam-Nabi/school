<?php

namespace App\Notifications;

use App\Filament\Student\Pages\MyAttendanceRanking;
use App\Models\Attendance;
use App\Notifications\Channels\PerDeviceWebPushChannel;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StudentAttendanceMarkedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Attendance $attendance,
    ) {}

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
            'title' => 'Attendance Marked',
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
            ->title('Attendance Marked')
            ->body($this->buildBody())
            ->color($this->attendance->status->getColor())
            ->icon($this->attendance->status->getIcon())
            ->getDatabaseMessage() + [
                'attendance_id' => $this->attendance->id,
                'status' => $this->attendance->status->value,
                'date' => $this->attendance->date?->toDateString(),
                'class_id' => $this->attendance->class_id,
            ];
    }

    private function buildBody(): string
    {
        $date = $this->attendance->date?->toDateString();
        $statusLabel = $this->attendance->status->getLabel();

        return "You were marked {$statusLabel} on {$date}.";
    }
}
