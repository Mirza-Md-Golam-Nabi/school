<?php

namespace App\Actions\ExamNotifications;

use App\Actions\ResolveSubjectOutcome;
use App\Actions\Sms\Concerns\BuildsStudentSms;
use App\Enums\SmsEvent;
use App\Enums\SmsLanguage;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\GradeScale;
use App\Models\SmsSetting;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Support\ExamNotifications\ExamNotificationMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ComposeExamSubjectMarksMessagesAction
{
    use BuildsStudentSms;

    public function __construct(private readonly ResolveSubjectOutcome $resolveSubjectOutcome) {}

    /**
     * One message per active student carrying their marks in every chosen
     * subject (in the Subjects page order). A subject the student has no
     * marks entered for yet is left out of their message; a student with no
     * marks in any chosen subject gets nothing.
     *
     * @param  list<int>  $subjectIds
     * @return array{messages: Collection<int, ExamNotificationMessage>, students_without_marks: int}
     */
    public function handle(Exam $exam, array $subjectIds): array
    {
        $exam->loadMissing(['examType:id,name', 'class:id,name']);
        $setting = SmsSetting::current();
        $language = $setting->smsLanguage();
        $gradeScales = GradeScale::cached();

        $configs = ExamSubjectConfig::query()
            ->where('exam_id', $exam->id)
            ->whereIn('subject_id', $subjectIds)
            ->with('subject:id,name,name_bn,sort_order')
            ->get()
            ->sortBy(fn (ExamSubjectConfig $config): array => [(int) $config->subject?->sort_order, (int) $config->subject_id])
            ->values();

        $resultsByStudent = StudentResult::query()
            ->where('exam_id', $exam->id)
            ->whereIn('subject_id', $configs->pluck('subject_id'))
            ->whereHas('student', fn (Builder $query) => $query->active())
            ->with(['student.user:id,name,phone', 'student.class:id,name'])
            ->get()
            ->filter(fn (StudentResult $result): bool => $this->hasMarks($result))
            ->groupBy('student_id');

        $messages = $resultsByStudent
            ->map(function (Collection $results) use ($exam, $configs, $gradeScales, $setting, $language): ?ExamNotificationMessage {
                $resultsBySubject = $results->keyBy('subject_id');

                $lines = $configs
                    ->map(fn (ExamSubjectConfig $config): ?string => $resultsBySubject->has($config->subject_id)
                        ? $this->markLine($config, $resultsBySubject->get($config->subject_id), $gradeScales, $language)
                        : null)
                    ->filter()
                    ->values();

                if ($lines->isEmpty()) {
                    return null;
                }

                /** @var StudentProfile $student */
                $student = $results->first()->student;
                $marks = $lines->implode(', ');
                $examName = (string) $exam->examType?->name;

                return new ExamNotificationMessage(
                    student: $student,
                    pushBody: "{$examName}: {$marks}",
                    smsText: $setting->render(SmsEvent::ExamSubjectMarks, $this->studentPlaceholders($student) + [
                        'roll' => (string) $student->roll_no,
                        'exam' => $examName,
                        'marks' => $marks,
                    ]),
                );
            })
            ->filter()
            ->values();

        return [
            'messages' => $messages,
            'students_without_marks' => $this->countStudentsWithoutMarks($exam, $configs, $resultsByStudent),
        ];
    }

    /**
     * "গণিত 72/100 (A)", or "গণিত অনুপস্থিত" for an absent student.
     *
     * @param  Collection<int, GradeScale>  $gradeScales
     */
    private function markLine(ExamSubjectConfig $config, StudentResult $result, Collection $gradeScales, SmsLanguage $language): string
    {
        $name = $this->subjectName($config, $language);

        if (! $result->hasWeightedResult() && $result->is_absent) {
            return $name.' '.($language === SmsLanguage::Bangla ? 'অনুপস্থিত' : 'Absent');
        }

        $outcome = $this->resolveSubjectOutcome->execute($result, $config, $gradeScales);
        $fullMarks = $result->resolveFullMarks((float) ($config->total_marks ?: 100));
        $grade = $outcome['grade']?->letter_grade;

        return sprintf(
            '%s %s/%s%s',
            $name,
            $this->formatNumber($result->effective_marks),
            $this->formatNumber($fullMarks),
            $grade ? " ({$grade})" : '',
        );
    }

    /**
     * The subject's name in the SMS language: the name written for this
     * exam first, then the subject's own name.
     */
    private function subjectName(ExamSubjectConfig $config, SmsLanguage $language): string
    {
        $candidates = $language === SmsLanguage::Bangla
            ? [$config->subject_name_bn, $config->subject?->name_bn, $config->subject_name, $config->subject?->name]
            : [$config->subject_name, $config->subject?->name];

        return (string) collect($candidates)->first(fn (?string $name): bool => filled($name), '—');
    }

    /**
     * A row saved before any marks were typed in is not a result yet.
     */
    private function hasMarks(StudentResult $result): bool
    {
        return $result->is_absent
            || $result->hasWeightedResult()
            || $result->mcq_marks !== null
            || $result->written_marks !== null
            || $result->practical_marks !== null;
    }

    private function formatNumber(float $number): string
    {
        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    /**
     * Active students of the exam's class still missing marks in at least
     * one of the chosen subjects — shown to the admin as a warning.
     *
     * @param  Collection<int, ExamSubjectConfig>  $configs
     * @param  Collection<int, Collection<int, StudentResult>>  $resultsByStudent
     */
    private function countStudentsWithoutMarks(Exam $exam, Collection $configs, Collection $resultsByStudent): int
    {
        if ($configs->isEmpty()) {
            return 0;
        }

        return StudentProfile::query()
            ->active()
            ->where('current_class_id', $exam->class_id)
            ->pluck('id')
            ->filter(fn (int $studentId): bool => ($resultsByStudent->get($studentId)?->count() ?? 0) < $configs->count())
            ->count();
    }
}
