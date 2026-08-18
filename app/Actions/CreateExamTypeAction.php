<?php

namespace App\Actions;

use App\Enums\ExamConfigType;
use App\Models\Classes;
use App\Models\ExamContributeRule;
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

        $classIds = [];

        if ($data['type'] === ExamConfigType::Supporting->value) {
            $classIds = $data['class_id'];

            foreach ($classIds as $classId) {
                ExamContributeRule::create([
                    'class_id' => $classId,
                    'source_exam_type_id' => $examType->id,
                    'target_exam_type_id' => $data['target_exam_type_id'],
                    'contribution_percent' => $data['contribution_percent'],
                    'session_year' => $data['session_year'],
                ]);
            }
        }

        activity('exam_type')
            ->performedOn($examType)
            ->event('created')
            ->withProperties([
                'attributes' => $this->buildLoggedAttributes($data, $config->type, $config->count_method, $config->best_n_count, $classIds),
            ])
            ->log("Created exam type \"{$examType->name}\".");

        return $examType;
    }

    /**
     * @param  array<int>  $classIds
     * @return array<string, mixed>
     */
    private function buildLoggedAttributes(array $data, mixed $type, mixed $countMethod, mixed $bestNCount, array $classIds): array
    {
        return [
            'name' => $data['name'],
            'type' => $type?->value,
            'count_method' => $countMethod?->value,
            'best_n_count' => $bestNCount,
            'class_id' => $classIds ?: null,
            'class_id_label' => $classIds !== [] ? Classes::whereIn('id', $classIds)->pluck('name')->all() : null,
            'target_exam_type_id' => $data['target_exam_type_id'] ?? null,
            'target_exam_type_id_label' => isset($data['target_exam_type_id'])
                ? ExamType::find($data['target_exam_type_id'])?->name
                : null,
            'contribution_percent' => $data['contribution_percent'] ?? null,
            'session_year' => $data['session_year'] ?? null,
        ];
    }
}
