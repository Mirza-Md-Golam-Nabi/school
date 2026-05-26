<?php

namespace App\Actions;

use App\Models\Exam;

class CreateExamAction
{
    public function handle(array $data): Exam
    {
        return Exam::create([
            'exam_type_id' => $data['exam_type_id'],
            'class_id' => $data['class_id'],
            'session_year' => $data['session_year'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_published' => $data['is_published'] ?? false,
        ]);
    }
}
