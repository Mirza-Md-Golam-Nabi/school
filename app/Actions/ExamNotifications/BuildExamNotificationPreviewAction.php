<?php

namespace App\Actions\ExamNotifications;

use App\Actions\Sms\Concerns\BuildsStudentSms;
use App\Enums\ExamNotificationChannel;
use App\Enums\ExamNotificationKind;
use App\Models\Exam;
use App\Models\ExamNotificationBatch;
use App\Models\SmsSetting;
use App\Support\ExamNotifications\ExamNotificationMessage;
use App\Support\Sms\BangladeshPhone;
use App\Support\Sms\SmsSegments;
use Illuminate\Support\Collection;

class BuildExamNotificationPreviewAction
{
    use BuildsStudentSms;

    public function __construct(
        private readonly ComposeExamSubjectMarksMessagesAction $composeSubjectMarks,
        private readonly ComposeExamResultMessagesAction $composeResult,
    ) {}

    /**
     * What a send would do, for the admin to check before confirming: who
     * gets it, what SMS would cost against the balance, whose marks are
     * still missing, a sample message and when it was last sent.
     *
     * @param  list<int>  $subjectIds
     * @param  list<ExamNotificationChannel>  $channels
     * @return array{recipients: int, students_without_marks: int, sms_requested: bool, sms_blocked_reason: ?string, sms_count: int, sms_without_phone: int, sms_parts: int, sms_cost: float, sms_balance: float, sample_push: ?string, sample_sms: ?string, last_sent_at: ?string}
     */
    public function handle(Exam $exam, ExamNotificationKind $kind, array $subjectIds, array $channels): array
    {
        if ($kind === ExamNotificationKind::SubjectMarks) {
            ['messages' => $messages, 'students_without_marks' => $withoutMarks] = $this->composeSubjectMarks->handle($exam, $subjectIds);
        } else {
            $messages = $this->composeResult->handle($exam);
            $withoutMarks = 0;
        }

        $setting = SmsSetting::current();
        $smsRequested = in_array(ExamNotificationChannel::Sms, $channels, true);
        $smsTexts = $this->deliverableSmsTexts($messages);
        $parts = (int) $smsTexts->sum(fn (string $text): int => SmsSegments::count($text));

        return [
            'recipients' => $messages->count(),
            'students_without_marks' => $withoutMarks,
            'sms_requested' => $smsRequested,
            'sms_blocked_reason' => $smsRequested ? $this->smsBlockedReason($setting, $kind) : null,
            'sms_count' => $smsTexts->count(),
            'sms_without_phone' => $messages->count() - $smsTexts->count(),
            'sms_parts' => $parts,
            'sms_cost' => round($parts * $setting->ratePerSms(), 2),
            'sms_balance' => $setting->currentBalance(),
            'sample_push' => $messages->first()?->pushBody,
            'sample_sms' => $messages->first()?->smsText,
            'last_sent_at' => $this->lastSentAt($exam, $kind, $subjectIds),
        ];
    }

    /**
     * SMS texts that would actually go out: one per valid phone number, the
     * same text to the same number counted once (as the SMS queue does).
     *
     * @param  Collection<int, ExamNotificationMessage>  $messages
     * @return Collection<int, string>
     */
    private function deliverableSmsTexts(Collection $messages): Collection
    {
        return $messages
            ->map(fn (ExamNotificationMessage $message): ?array => ($phone = BangladeshPhone::normalize($this->studentPhone($message->student)))
                ? ['key' => $phone.'|'.$message->smsText, 'text' => $message->smsText]
                : null)
            ->filter()
            ->unique('key')
            ->pluck('text')
            ->values();
    }

    private function smsBlockedReason(SmsSetting $setting, ExamNotificationKind $kind): ?string
    {
        return match (true) {
            ! $setting->is_enabled || $setting->ratePerSms() <= 0 => 'SMS Settings-এ SMS বন্ধ আছে — SMS যাবে না।',
            ! $setting->isEventEnabled($kind->smsEvent()) => "SMS Settings-এ \"{$kind->smsEvent()->getLabel()}\" event চালু নেই — SMS যাবে না।",
            default => null,
        };
    }

    /**
     * When this kind was last sent — for subject marks, the latest send that
     * included any of the chosen subjects.
     *
     * @param  list<int>  $subjectIds
     */
    private function lastSentAt(Exam $exam, ExamNotificationKind $kind, array $subjectIds): ?string
    {
        $batch = ExamNotificationBatch::query()
            ->where('exam_id', $exam->id)
            ->where('kind', $kind)
            ->latest('id')
            ->get()
            ->first(fn (ExamNotificationBatch $batch): bool => $kind === ExamNotificationKind::Result
                || collect($subjectIds)->contains(fn (int $subjectId): bool => $batch->includesSubject($subjectId)));

        return $batch?->created_at?->format('d M Y, h:i A');
    }
}
