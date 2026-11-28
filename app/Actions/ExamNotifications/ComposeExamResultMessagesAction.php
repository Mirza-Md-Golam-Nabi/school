<?php

namespace App\Actions\ExamNotifications;

use App\Actions\Sms\Concerns\BuildsStudentSms;
use App\Enums\SmsEvent;
use App\Enums\SmsLanguage;
use App\Models\Exam;
use App\Models\GradeScale;
use App\Models\SmsSetting;
use App\Models\StudentMeritRanking;
use App\Support\ExamNotifications\ExamNotificationMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ComposeExamResultMessagesAction
{
    use BuildsStudentSms;

    /**
     * One message per active student with a merit ranking in the exam: GPA,
     * grade, total marks and class position — the same numbers the
     * marksheet prints. Students only have a ranking once "Calculate
     * Rankings" has run.
     *
     * @return Collection<int, ExamNotificationMessage>
     */
    public function handle(Exam $exam): Collection
    {
        $exam->loadMissing(['examType:id,name', 'class:id,name']);
        $setting = SmsSetting::current();
        $language = $setting->smsLanguage();
        $gradeScales = GradeScale::cached();
        $examName = (string) $exam->examType?->name;

        return StudentMeritRanking::query()
            ->where('exam_id', $exam->id)
            ->whereHas('student', fn (Builder $query) => $query->active())
            ->with(['student.user:id,name,phone', 'student.class:id,name'])
            ->orderBy('class_rank')
            ->get()
            ->map(function (StudentMeritRanking $ranking) use ($examName, $gradeScales, $setting, $language): ExamNotificationMessage {
                $student = $ranking->student;
                $values = [
                    'gpa' => number_format((float) $ranking->gpa, 2),
                    'grade' => (string) (GradeScale::fromGpa((float) $ranking->gpa, $gradeScales)?->letter_grade ?? 'F'),
                    'total_marks' => rtrim(rtrim(number_format((float) $ranking->total_marks, 2, '.', ''), '0'), '.'),
                    'position' => (string) ($ranking->class_rank ?? '—'),
                ];

                return new ExamNotificationMessage(
                    student: $student,
                    pushBody: $this->pushBody($examName, $values, $language),
                    smsText: $setting->render(SmsEvent::ExamResult, $this->studentPlaceholders($student) + [
                        'roll' => (string) $student->roll_no,
                        'exam' => $examName,
                    ] + $values),
                );
            })
            ->values();
    }

    /**
     * @param  array{gpa: string, grade: string, total_marks: string, position: string}  $values
     */
    private function pushBody(string $examName, array $values, SmsLanguage $language): string
    {
        return $language === SmsLanguage::Bangla
            ? "{$examName}: GPA {$values['gpa']} ({$values['grade']}), মোট নম্বর {$values['total_marks']}, মেধাক্রম {$values['position']}"
            : "{$examName}: GPA {$values['gpa']} ({$values['grade']}), Total {$values['total_marks']}, Position {$values['position']}";
    }
}
