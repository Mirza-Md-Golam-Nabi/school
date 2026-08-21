<?php

namespace App\Notifications\Concerns;

use App\Models\Exam;
use App\Models\StudentProfile;
use App\Notifications\ExamResultPublishedNotification;

/**
 * Shared by every place is_published can flip to true — CreateExamAction
 * (an exam created already published) and EditExam::handleRecordUpdate()
 * (the only place it's toggled after creation) — so the "who to notify"
 * and "how to notify" logic exists in exactly one place.
 */
trait NotifiesExamResultPublished
{
    protected function notifyExamResultPublished(Exam $exam): void
    {
        StudentProfile::active()
            ->where('current_class_id', $exam->class_id)
            ->with('user')
            ->get(['id', 'user_id'])
            ->each(fn (StudentProfile $student) => $student->user?->notify(new ExamResultPublishedNotification($exam)));
    }
}
