<?php

namespace App\Actions;

use App\Models\ExamType;

class UpdateExamTypeAction
{
    public function handle(ExamType $examType, array $data): ExamType
    {
        $oldConfig = $examType->examTypeConfig;

        $old = [
            'name' => $examType->name,
            'type' => $oldConfig?->type?->value,
            'count_method' => $oldConfig?->count_method?->value,
            'best_n_count' => $oldConfig?->best_n_count,
        ];

        $examType->disableLogging();
        $examType->update(['name' => $data['name']]);
        $examType->enableLogging();

        $examType->examTypeConfig()->update([
            'type' => $data['type'],
            'count_method' => $data['count_method'] ?? null,
            'best_n_count' => $data['best_n_count'] ?? null,
        ]);

        activity('exam_type')
            ->performedOn($examType)
            ->event('updated')
            ->withProperties([
                'attributes' => [
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'count_method' => $data['count_method'] ?? null,
                    'best_n_count' => $data['best_n_count'] ?? null,
                ],
                'old' => $old,
            ])
            ->log("Updated exam type \"{$examType->name}\".");

        return $examType;
    }
}
