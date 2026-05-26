<?php

namespace App\Actions;

use App\Enums\ExamConfigType;
use App\Models\ExamContributeRule;
use App\Models\ExamType;

class CreateExamTypeAction
{
    public function handle(array $data): ExamType
    {
        $examType = ExamType::create(['name' => $data['name']]);

        $examType->examTypeConfig()->create([
            'type' => $data['type'],
            'count_method' => $data['count_method'] ?? null,
            'best_n_count' => $data['best_n_count'] ?? null,
        ]);

        if ($data['type'] === ExamConfigType::Supporting->value) {
            foreach ($data['class_id'] as $classId) {
                ExamContributeRule::create([
                    'class_id' => $classId,
                    'source_exam_type_id' => $examType->id,
                    'target_exam_type_id' => $data['target_exam_type_id'],
                    'contribution_percent' => $data['contribution_percent'],
                    'session_year' => $data['session_year'],
                ]);
            }
        }

        return $examType;
    }
}
