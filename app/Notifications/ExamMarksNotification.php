<?php

namespace App\Notifications;

use App\Enums\ExamNotificationKind;
use App\Filament\Student\Pages\Dashboard;
use App\Filament\Student\Resources\ExamResults\ExamResultResource;
use App\Models\Exam;
use App\Notifications\Channels\PerDeviceWebPushChannel;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * A student's own marks — one or more subjects, or the complete result —
 * sent by the admin from inside an exam.
 */
class ExamMarksNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Exam $exam,
        public readonly ExamNotificationKind $kind,
        public readonly string $body,
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
            'title' => $this->title(),
            'icon' => '/icons/192x192.png',
            'body' => $this->body,
            'data' => [
                'url' => $this->viewUrl(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->title())
            ->body($this->body)
            ->success()
            ->icon($this->kind === ExamNotificationKind::Result ? 'heroicon-o-trophy' : 'heroicon-o-document-text')
            ->actions([
                Action::make('view')
                    ->label($this->exam->is_published ? 'View Result' : 'Open')
                    ->button()
                    ->url($this->viewUrl())
                    ->markAsRead(),
            ])
            ->getDatabaseMessage() + [
                'exam_id' => $this->exam->id,
                'kind' => $this->kind->value,
            ];
    }

    private function title(): string
    {
        return $this->kind === ExamNotificationKind::Result ? 'Exam Result' : 'Exam Marks';
    }

    /**
     * Students only see an exam's result page once it is published, so
     * marks sent before that open the dashboard instead.
     */
    private function viewUrl(): string
    {
        return $this->exam->is_published
            ? ExamResultResource::getUrl('view', ['record' => $this->exam->id], panel: 'student')
            : Dashboard::getUrl(panel: 'student');
    }
}
