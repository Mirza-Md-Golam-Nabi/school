<?php

namespace App\Filament\Student\Concerns;

use App\Models\Notice;
use App\Models\NoticeRead;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

trait InteractsWithNotices
{
    protected function currentStudent(): ?StudentProfile
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->studentProfile;
    }

    protected function authorizedNotice(int $noticeId): Notice
    {
        $student = $this->currentStudent();

        abort_unless($student !== null, 403);

        return Notice::query()
            ->published()
            ->visibleToStudent($student)
            ->findOrFail($noticeId);
    }

    public function getNoticeDetail(int $noticeId): View
    {
        return view('filament.shared.notice-detail', ['notice' => $this->authorizedNotice($noticeId)]);
    }

    public function markAsRead(int $noticeId): void
    {
        $notice = $this->authorizedNotice($noticeId);

        NoticeRead::updateOrCreate(
            ['notice_id' => $notice->id, 'user_id' => Auth::id()],
            ['read_at' => now()]
        );
    }
}
