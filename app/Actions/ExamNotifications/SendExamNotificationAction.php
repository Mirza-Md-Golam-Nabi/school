<?php

namespace App\Actions\ExamNotifications;

use App\Actions\Sms\Concerns\BuildsStudentSms;
use App\Actions\Sms\QueueSmsBatchAction;
use App\Enums\ExamNotificationChannel;
use App\Enums\ExamNotificationKind;
use App\Models\Exam;
use App\Models\ExamNotificationBatch;
use App\Models\SmsSetting;
use App\Models\User;
use App\Notifications\ExamMarksNotification;
use App\Support\ExamNotifications\ExamNotificationMessage;
use App\Support\Sms\OutgoingSms;
use App\Support\Sms\SmsBatchResult;
use Illuminate\Support\Collection;

class SendExamNotificationAction
{
    use BuildsStudentSms;

    public function __construct(
        private readonly ComposeExamSubjectMarksMessagesAction $composeSubjectMarks,
        private readonly ComposeExamResultMessagesAction $composeResult,
        private readonly QueueSmsBatchAction $queueSmsBatch,
    ) {}

    /**
     * Sends a student's marks (chosen subjects) or complete result through
     * the chosen channels: push to the student's app, SMS to the guardian.
     * SMS goes out only while the SMS event is switched on in SMS Settings,
     * and as one all-or-nothing batch. Every send is recorded; with nobody
     * to send to, nothing is sent or recorded.
     *
     * @param  list<int>  $subjectIds  Only for subject marks.
     * @param  list<ExamNotificationChannel>  $channels
     * @return array{batch: ?ExamNotificationBatch, sms: ?SmsBatchResult}
     */
    public function handle(Exam $exam, ExamNotificationKind $kind, array $subjectIds, array $channels, ?User $sentBy = null): array
    {
        $messages = $this->compose($exam, $kind, $subjectIds);

        if ($messages->isEmpty()) {
            return ['batch' => null, 'sms' => null];
        }

        $pushCount = in_array(ExamNotificationChannel::Push, $channels, true)
            ? $this->sendPush($exam, $kind, $messages)
            : 0;

        $smsResult = in_array(ExamNotificationChannel::Sms, $channels, true)
            ? $this->sendSms($exam, $kind, $messages)
            : null;

        $batch = ExamNotificationBatch::create([
            'exam_id' => $exam->id,
            'kind' => $kind,
            'subject_ids' => $kind === ExamNotificationKind::SubjectMarks ? array_values(array_map('intval', $subjectIds)) : null,
            'channels' => array_map(fn (ExamNotificationChannel $channel): string => $channel->value, $channels),
            'sent_by' => $sentBy?->id,
            'recipients_count' => $messages->count(),
            'push_count' => $pushCount,
            'sms_count' => $smsResult?->isQueued() ? $smsResult->queued : 0,
            'sms_status' => $smsResult?->status,
            'sms_cost' => $smsResult?->isQueued() ? $smsResult->cost : 0,
        ]);

        return ['batch' => $batch, 'sms' => $smsResult];
    }

    /**
     * @param  list<int>  $subjectIds
     * @return Collection<int, ExamNotificationMessage>
     */
    public function compose(Exam $exam, ExamNotificationKind $kind, array $subjectIds): Collection
    {
        return $kind === ExamNotificationKind::SubjectMarks
            ? $this->composeSubjectMarks->handle($exam, $subjectIds)['messages']
            : $this->composeResult->handle($exam);
    }

    /**
     * @param  Collection<int, ExamNotificationMessage>  $messages
     */
    private function sendPush(Exam $exam, ExamNotificationKind $kind, Collection $messages): int
    {
        $sent = 0;

        foreach ($messages as $message) {
            $user = $message->student->user;

            if (! $user) {
                continue;
            }

            $user->notify(new ExamMarksNotification($exam, $kind, $message->pushBody));
            $sent++;
        }

        return $sent;
    }

    /**
     * @param  Collection<int, ExamNotificationMessage>  $messages
     */
    private function sendSms(Exam $exam, ExamNotificationKind $kind, Collection $messages): SmsBatchResult
    {
        if (! SmsSetting::current()->isEventEnabled($kind->smsEvent())) {
            return new SmsBatchResult(SmsBatchResult::DISABLED);
        }

        return $this->queueSmsBatch->handle(
            $messages->map(fn (ExamNotificationMessage $message): OutgoingSms => new OutgoingSms(
                phone: $this->studentPhone($message->student),
                message: $message->smsText,
                event: $kind->smsEvent(),
                sourceId: $exam->id,
                recipientName: $message->student->user?->name,
            )),
            alertAdminsOnRejection: false,
        );
    }
}
