<?php

namespace App\Actions;

use App\Models\Exam;

class SyncClassSubjectMergesAction
{
    public function __construct(private readonly CopySubjectMergesToExamAction $copySubjectMergesToExam) {}

    /**
     * Brings the class's default subject merges (or every class's, when no
     * class is given) to each of its exams that still follows the default:
     * not published, and not changed on the exam itself.
     *
     * @return int how many exams were brought up to date
     */
    public function handle(?int $classId = null): int
    {
        $exams = Exam::query()
            ->where('is_published', false)
            ->where('has_custom_subject_merges', false)
            ->when($classId, fn ($query) => $query->where('class_id', $classId))
            ->get();

        $exams->each(fn (Exam $exam) => $this->copySubjectMergesToExam->handle($exam));

        return $exams->count();
    }
}
