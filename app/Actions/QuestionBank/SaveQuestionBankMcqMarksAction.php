<?php

namespace App\Actions\QuestionBank;

use App\Actions\ResolveEligibleStudentsForSubject;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\StudentResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SaveQuestionBankMcqMarksAction
{
    public const SKIP_NOT_IN_SUBJECT = 'not_in_subject';

    public const SKIP_OVER_FULL_MARKS = 'over_full_marks';

    public const SKIP_HAS_MARKS = 'has_marks';

    public const SKIP_HAS_OTHER_MARKS = 'has_other_marks';

    public function __construct(private ResolveEligibleStudentsForSubject $eligibleStudents) {}

    /**
     * Question Bank-এর অনলাইন MCQ পরীক্ষার নম্বর এই পরীক্ষার এই বিষয়ের রেজাল্টের MCQ ঘরে বসায়।
     *
     * - প্রকাশিত পরীক্ষায় কিছুই বদলায় না।
     * - শুধু MCQ ঘর: লিখিত/ব্যবহারিক নম্বর কখনো ছোঁয় না।
     * - যে ছাত্রের ঘরে আগে থেকে MCQ নম্বর (বা অনুপস্থিত) আছে, `$overwrite` ছাড়া তাকে বদলায় না।
     * - অনুপস্থিত: পুরো বিষয়ে অনুপস্থিত দাগায় — তাই লিখিত/ব্যবহারিক নম্বর থাকলে দাগায় না।
     * - একই নম্বর আবার এলে একই ফল — দুইবার পাঠালেও একবারের মতোই।
     *
     * @param  list<array{student_id: int, mcq_marks: float|null, absent: bool}>  $marks
     * @return array{saved: int, skipped: list<array{student_id: int, reason: string}>}
     *
     * @throws RuntimeException when the exam's result is already published
     */
    public function handle(Exam $exam, ExamSubjectConfig $config, array $marks, bool $overwrite): array
    {
        if ($exam->is_published) {
            throw new RuntimeException('The exam result is already published.');
        }

        $eligibleIds = $this->eligibleStudents->execute($exam->class_id, $config->subject_id)->pluck('id')->flip();

        $existing = StudentResult::query()
            ->where('exam_id', $exam->id)
            ->where('subject_id', $config->subject_id)
            ->whereIn('student_id', array_column($marks, 'student_id'))
            ->get()
            ->keyBy('student_id');

        $saved = 0;
        $skipped = [];

        DB::transaction(function () use ($exam, $config, $marks, $overwrite, $eligibleIds, $existing, &$saved, &$skipped): void {
            foreach ($marks as $mark) {
                $studentId = (int) $mark['student_id'];
                $result = $existing->get($studentId);
                $reason = $this->reasonToSkip($mark, $result, $config, $overwrite, $eligibleIds->has($studentId));

                if ($reason !== null) {
                    $skipped[] = ['student_id' => $studentId, 'reason' => $reason];

                    continue;
                }

                $result ??= new StudentResult(['exam_id' => $exam->id, 'subject_id' => $config->subject_id, 'student_id' => $studentId]);
                $result->fill([
                    'class_id' => $exam->class_id,
                    'is_absent' => (bool) $mark['absent'],
                    'mcq_marks' => $mark['absent'] ? null : round((float) $mark['mcq_marks'], 2),
                ])->save();

                $saved++;
            }
        });

        Log::info('MCQ marks received from the Question Bank.', [
            'exam_id' => $exam->id,
            'subject_id' => $config->subject_id,
            'saved' => $saved,
            'skipped' => count($skipped),
            'overwrite' => $overwrite,
        ]);

        return ['saved' => $saved, 'skipped' => $skipped];
    }

    /**
     * @param  array{student_id: int, mcq_marks: float|null, absent: bool}  $mark
     */
    private function reasonToSkip(array $mark, ?StudentResult $result, ExamSubjectConfig $config, bool $overwrite, bool $isEligible): ?string
    {
        if (! $isEligible) {
            return self::SKIP_NOT_IN_SUBJECT;
        }

        if (! $mark['absent'] && ((float) $mark['mcq_marks'] < 0 || (float) $mark['mcq_marks'] > (float) $config->mcq_total)) {
            return self::SKIP_OVER_FULL_MARKS;
        }

        $hasOtherMarks = $result !== null && ($result->written_marks !== null || $result->practical_marks !== null);

        if ($mark['absent'] && $hasOtherMarks) {
            return self::SKIP_HAS_OTHER_MARKS;
        }

        $alreadyFilled = $result !== null && ($result->mcq_marks !== null || $result->is_absent);
        $isSame = $result !== null && $result->is_absent === (bool) $mark['absent']
            && ($mark['absent'] || (float) $result->mcq_marks === round((float) $mark['mcq_marks'], 2));

        return $alreadyFilled && ! $isSame && ! $overwrite ? self::SKIP_HAS_MARKS : null;
    }
}
