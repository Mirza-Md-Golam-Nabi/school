<?php

namespace App\Support\ExamNotifications;

use App\Models\StudentProfile;

/**
 * What one student gets from an exam notification: the push text for the
 * student's app and the SMS text for the guardian.
 */
final readonly class ExamNotificationMessage
{
    public function __construct(
        public StudentProfile $student,
        public string $pushBody,
        public string $smsText,
    ) {}
}
