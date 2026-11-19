<?php

namespace App\Actions\Sms;

use App\Actions\Sms\Concerns\BuildsStudentSms;
use App\Enums\SmsEvent;
use App\Models\Attendance;
use App\Models\SmsSetting;
use App\Models\StudentProfile;
use App\Support\Sms\OutgoingSms;
use App\Support\Sms\SmsBatchResult;
use Illuminate\Support\Carbon;

class SendAttendanceSmsAction
{
    use BuildsStudentSms;

    public function __construct(private QueueSmsBatchAction $queueSmsBatch) {}

    /**
     * Sends attendance SMS to the guardians of the given students as one
     * all-or-nothing batch. Events the admin has switched off are dropped.
     *
     * @param  iterable<array{student: StudentProfile, attendance: Attendance, event: SmsEvent, time?: ?Carbon}>  $items
     */
    public function handle(iterable $items): ?SmsBatchResult
    {
        $setting = SmsSetting::current();
        $messages = [];

        foreach ($items as $item) {
            $event = $item['event'];

            if (! $setting->isEventEnabled($event)) {
                continue;
            }

            $student = $item['student'];
            $student->loadMissing(['user:id,name,phone', 'class:id,name']);
            $time = $item['time'] ?? null;

            $messages[] = new OutgoingSms(
                phone: $this->studentPhone($student),
                message: $setting->render($event, $this->studentPlaceholders($student) + [
                    'date' => Carbon::parse($item['attendance']->date)->format('d/m/Y'),
                    'time' => $time?->format('h:i A') ?? '',
                ]),
                event: $event,
                sourceId: $item['attendance']->id,
                recipientName: $student->user?->name,
            );
        }

        return $messages === [] ? null : $this->queueSmsBatch->handle($messages);
    }
}
