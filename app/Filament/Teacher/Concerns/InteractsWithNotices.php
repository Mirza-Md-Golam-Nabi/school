<?php

namespace App\Filament\Teacher\Concerns;

use App\Models\Notice;
use App\Models\NoticeRead;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

trait InteractsWithNotices
{
    protected function currentTeacher(): ?TeacherProfile
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->teacherProfile;
    }

    protected function authorizedNotice(int $noticeId): Notice
    {
        $teacher = $this->currentTeacher();

        abort_unless($teacher !== null, 403);

        return Notice::query()
            ->published()
            ->visibleToTeacher($teacher)
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
