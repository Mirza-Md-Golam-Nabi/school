<?php

namespace App\Notifications\Concerns;

use App\Enums\NoticeTargetType;
use App\Models\Classes;
use App\Models\Notice;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Notifications\NoticeCreatedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Shared by every place a Notice actually becomes visible to its audience —
 * CreateNotice::afterCreate() (published immediately) and
 * PublishNoticeJob::handle() (a scheduled notice's publish time arriving) —
 * so recipient resolution and dispatch exist in exactly one place.
 */
trait NotifiesNoticeCreated
{
    protected function notifyNoticeCreated(Notice $notice): void
    {
        $recipients = $this->resolveNoticeRecipients($notice);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new NoticeCreatedNotification($notice));
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveNoticeRecipients(Notice $notice): Collection
    {
        return match ($notice->target_type) {
            NoticeTargetType::All => $this->activeStudentUsers()
                ->merge($this->activeTeacherUsers())
                ->merge($this->activeStaffUsers())
                ->unique('id')
                ->values(),
            NoticeTargetType::Students => $this->activeStudentUsers(),
            NoticeTargetType::Teachers => $this->activeTeacherUsers(),
            NoticeTargetType::ByClass => $this->activeStudentUsersInClasses(
                $notice->targets()->where('targetable_type', (new Classes)->getMorphClass())->pluck('targetable_id')
            ),
            NoticeTargetType::IndividualStudent, NoticeTargetType::IndividualTeacher => User::whereIn(
                'id',
                $notice->targets()->where('targetable_type', (new User)->getMorphClass())->pluck('targetable_id')
            )->get(),
        };
    }

    /**
     * @return Collection<int, User>
     */
    private function activeStudentUsers(): Collection
    {
        return StudentProfile::active()->with('user')->get(['id', 'user_id'])->pluck('user')->filter()->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function activeTeacherUsers(): Collection
    {
        return TeacherProfile::active()->with('user')->get(['id', 'user_id'])->pluck('user')->filter()->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function activeStaffUsers(): Collection
    {
        return StaffProfile::active()->with('user')->get(['id', 'user_id'])->pluck('user')->filter()->values();
    }

    /**
     * @param  Collection<int, int>  $classIds
     * @return Collection<int, User>
     */
    private function activeStudentUsersInClasses(Collection $classIds): Collection
    {
        if ($classIds->isEmpty()) {
            return collect();
        }

        return StudentProfile::active()
            ->whereIn('current_class_id', $classIds)
            ->with('user')
            ->get(['id', 'user_id', 'current_class_id'])
            ->pluck('user')
            ->filter()
            ->values();
    }
}
