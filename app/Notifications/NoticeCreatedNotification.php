<?php

namespace App\Notifications;

use App\Enums\UserType;
use App\Filament\Resources\Notices\NoticeResource;
use App\Filament\Student\Pages\Notices as StudentNotices;
use App\Filament\Teacher\Pages\Notices as TeacherNotices;
use App\Models\Notice;
use App\Notifications\Channels\PerDeviceWebPushChannel;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NoticeCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Notice $notice,
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
            'title' => 'New Notice: '.$this->notice->title,
            'icon' => '/icons/192x192.png',
            'body' => $this->buildBody(),
            'data' => [
                'url' => $this->buildViewUrl($notifiable),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('New Notice: '.$this->notice->title)
            ->body($this->buildBody())
            ->info()
            ->icon('heroicon-o-megaphone')
            ->actions([
                Action::make('view')
                    ->label('View Notice')
                    ->button()
                    ->url($this->buildViewUrl($notifiable))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage() + [
                'notice_id' => $this->notice->id,
            ];
    }

    /**
     * Students and teachers each have their own "Notices" page in their own
     * panel; staff share the admin panel and have no dedicated one, so they
     * get a deep link straight to the notice in the admin resource instead.
     */
    private function buildViewUrl(object $notifiable): string
    {
        return match ($notifiable->user_type) {
            UserType::Student => StudentNotices::getUrl(panel: 'student'),
            UserType::Teacher => TeacherNotices::getUrl(panel: 'teacher'),
            default => NoticeResource::getUrl('view', ['record' => $this->notice->id], panel: 'admin'),
        };
    }

    private function buildBody(): string
    {
        return Str::limit(strip_tags($this->notice->body), 150);
    }
}
