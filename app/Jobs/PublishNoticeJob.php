<?php

namespace App\Jobs;

use App\Models\Notice;
use App\Notifications\Concerns\NotifiesNoticeCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishNoticeJob implements ShouldQueue
{
    use NotifiesNoticeCreated;
    use Queueable;

    public function __construct(public readonly int $noticeId) {}

    public function handle(): void
    {
        $notice = Notice::find($this->noticeId);

        if (! $notice) {
            return;
        }

        // The notice was rescheduled to a later time (or its schedule was
        // cleared) after this delayed job was queued — a fresh job for the
        // new time already exists, so this stale run has nothing to do.
        if (! $notice->isPublished()) {
            return;
        }

        $this->notifyNoticeCreated($notice);
    }
}
