<?php

namespace App\Notifications\Concerns;

use App\Actions\ExamNotifications\SendExamNotificationAction;
use App\Enums\ExamNotificationKind;
use App\Models\Exam;
use App\Models\StudentProfile;
use App\Models\User;
use App\Notifications\ExamResultPublishedNotification;
use Illuminate\Support\Facades\Auth;

/**
 * Shared by every place is_published can flip to true — CreateExamAction
 * (an exam created already published) and EditExam::handleRecordUpdate()
 * (the only place it's toggled after creation) — so the "who to notify"
 * and "how to notify" logic exists in exactly one place.
 *
 * With "notify on publish" on, each student gets their own result through
 * the exam's result channels once rankings are calculated; before that only
 * a "result published" push goes out. With it off, nothing is sent — the
 * admin sends the result from the exam page.
 */
trait NotifiesExamResultPublished
{
    protected function notifyExamResultPublished(Exam $exam): void
    {
        if (! $exam->notify_result_on_publish) {
            return;
        }

        if ($exam->meritRankings()->exists()) {
            /** @var User|null $user */
            $user = Auth::user();

            app(SendExamNotificationAction::class)->handle($exam, ExamNotificationKind::Result, [], $exam->resultChannels(), $user);

            return;
        }

        StudentProfile::active()
            ->where('current_class_id', $exam->class_id)
            ->with('user')
            ->get(['id', 'user_id'])
            ->each(fn (StudentProfile $student) => $student->user?->notify(new ExamResultPublishedNotification($exam)));
    }
}
