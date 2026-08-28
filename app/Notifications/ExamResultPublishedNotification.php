<?php

namespace App\Notifications;

use App\Filament\Student\Resources\ExamResults\ExamResultResource;
use App\Models\Exam;
use App\Notifications\Channels\PerDeviceWebPushChannel;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ExamResultPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Exam $exam,
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
            'title' => 'Exam Result Published',
            'icon' => '/icons/192x192.png',
            'body' => $this->buildBody(),
            'data' => [
                'url' => $this->buildViewUrl(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Exam Result Published')
            ->body($this->buildBody())
            ->success()
            ->icon('heroicon-o-trophy')
            ->actions([
                Action::make('view')
                    ->label('View Result')
                    ->button()
                    ->url($this->buildViewUrl())
                    ->markAsRead(),
            ])
            ->getDatabaseMessage() + [
                'exam_id' => $this->exam->id,
                'class_id' => $this->exam->class_id,
                'session_year' => $this->exam->session_year,
            ];
    }

    private function buildViewUrl(): string
    {
        return ExamResultResource::getUrl('view', ['record' => $this->exam->id], panel: 'student');
    }

    private function buildBody(): string
    {
        return "Result for {$this->exam->displayLabel()} has been published.";
    }
}
