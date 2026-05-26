<?php

namespace App\Actions;

use App\Enums\ExamConfigType;
use App\Models\ExamType;

class UpdateExamTypeAction
{
    public function handle(ExamType $examType, array $data): ExamType
    {
        $examType->update(['name' => $data['name']]);

        $examType->examTypeConfig()->update([
            'type' => $data['type'],
            'count_method' => $data['count_method'] ?? null,
            'best_n_count' => $data['best_n_count'] ?? null,
        ]);

        $examType->contributeRules()->delete();

        if ($data['type'] === ExamConfigType::Supporting->value) {
            foreach ($data['class_id'] as $classId) {
                $examType->contributeRules()->create([
                    'class_id' => $classId,
                    'target_exam_type_id' => $data['target_exam_type_id'],
                    'contribution_percent' => $data['contribution_percent'],
                    'session_year' => $data['session_year'],
                ]);
            }
        }

        return $examType;
    }
}
