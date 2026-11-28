<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What an exam notification carries: the marks of one or more subjects, or
 * the student's complete result (GPA, grade, total, position).
 */
enum ExamNotificationKind: string implements HasLabel
{
    case SubjectMarks = 'subject_marks';
    case Result = 'result';

    public function getLabel(): string
    {
        return match ($this) {
            self::SubjectMarks => 'বিষয়ভিত্তিক মার্ক',
            self::Result => 'সম্পূর্ণ রেজাল্ট',
        };
    }

    public function smsEvent(): SmsEvent
    {
        return match ($this) {
            self::SubjectMarks => SmsEvent::ExamSubjectMarks,
            self::Result => SmsEvent::ExamResult,
        };
    }
}
