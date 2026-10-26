<?php

namespace App\Actions;

use App\Models\ExamType;

class CreateExamTypeAction
{
    public function handle(array $data): ExamType
    {
        $examType = new ExamType(['name' => $data['name']]);
        $examType->disableLogging();
        $examType->save();
        $examType->enableLogging();

        $config = $examType->examTypeConfig()->create([
            'type' => $data['type'],
            'count_method' => $data['count_method'] ?? null,
            'best_n_count' => $data['best_n_count'] ?? null,
        ]);

        activity('exam_type')
            ->performedOn($examType)
            ->event('created')
            ->withProperties([
                'attributes' => [
                    'name' => $data['name'],
                    'type' => $config->type?->value,
                    'count_method' => $config->count_method?->value,
                    'best_n_count' => $config->best_n_count,
                ],
            ])
            ->log("Created exam type \"{$examType->name}\".");

        return $examType;
    }
}
