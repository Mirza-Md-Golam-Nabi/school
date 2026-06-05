<?php

namespace App\Jobs;

use App\Models\Notice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishNoticeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $noticeId) {}

    public function handle(): void
    {
        $notice = Notice::find($this->noticeId);

        if (! $notice) {
            return;
        }

        // Already published or became a draft — nothing to do.
        if (! $notice->isScheduled()) {
            return;
        }

        // Notice is now past its publish time — mark as published (published_at stays).
        // Future extensions (SMS sending) would be triggered here.
    }
}
