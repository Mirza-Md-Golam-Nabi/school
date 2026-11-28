<?php

namespace App\Actions;

use App\Models\Exam;
use App\Notifications\Concerns\NotifiesExamResultPublished;

class CreateExamAction
{
    use NotifiesExamResultPublished;

    public function __construct(private readonly CopySubjectMergesToExamAction $copySubjectMergesToExam) {}

    public function handle(array $data): Exam
    {
        $exam = Exam::create([
            'exam_type_id' => $data['exam_type_id'],
            'class_id' => $data['class_id'],
            'session_year' => $data['session_year'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_published' => $data['is_published'] ?? false,
            'subject_marks_channels' => $data['subject_marks_channels'] ?? null,
            'result_channels' => $data['result_channels'] ?? null,
            'notify_result_on_publish' => (bool) ($data['notify_result_on_publish'] ?? true),
        ]);

        $this->copySubjectMergesToExam->handle($exam);

        if ($exam->is_published) {
            $this->notifyExamResultPublished($exam);
        }

        return $exam;
    }
}
