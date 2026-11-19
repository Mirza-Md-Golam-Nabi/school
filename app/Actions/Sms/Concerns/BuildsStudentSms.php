<?php

namespace App\Actions\Sms\Concerns;

use App\Models\StudentProfile;

trait BuildsStudentSms
{
    /**
     * A student's SMS goes to the guardian's number, or to the student's own
     * account number when no guardian number is on file.
     */
    protected function studentPhone(StudentProfile $student): ?string
    {
        return filled($student->guardian_phone) ? $student->guardian_phone : $student->user?->phone;
    }

    /**
     * The placeholders every student SMS can use.
     *
     * @return array{name: string, name_bn: string, student_id: string, class: string}
     */
    protected function studentPlaceholders(StudentProfile $student): array
    {
        $name = (string) $student->user?->name;

        return [
            'name' => $name,
            'name_bn' => filled($student->name_bn) ? $student->name_bn : $name,
            'student_id' => (string) $student->student_id_no,
            'class' => (string) $student->class?->name,
        ];
    }

    protected function formatTaka(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }
}
