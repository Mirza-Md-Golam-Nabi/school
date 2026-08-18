<?php

namespace App\Actions;

use App\Enums\ExamConfigType;
use App\Models\Classes;
use App\Models\ExamType;

class UpdateExamTypeAction
{
    public function handle(ExamType $examType, array $data): ExamType
    {
        $oldConfig = $examType->examTypeConfig;
        $oldRules = $examType->contributeRules;
        $oldClassIds = $oldRules->pluck('class_id')->all();

        $old = [
            'name' => $examType->name,
            'type' => $oldConfig?->type?->value,
            'count_method' => $oldConfig?->count_method?->value,
            'best_n_count' => $oldConfig?->best_n_count,
            'class_id' => $oldClassIds ?: null,
            'class_id_label' => $oldClassIds !== [] ? Classes::whereIn('id', $oldClassIds)->pluck('name')->all() : null,
            'target_exam_type_id' => $oldRules->first()?->target_exam_type_id,
            'target_exam_type_id_label' => $oldRules->first()?->targetExamType?->name,
            'contribution_percent' => $oldRules->first()?->contribution_percent,
            'session_year' => $oldRules->first()?->session_year,
        ];

        $examType->disableLogging();
        $examType->update(['name' => $data['name']]);
        $examType->enableLogging();

        $examType->examTypeConfig()->update([
            'type' => $data['type'],
            'count_method' => $data['count_method'] ?? null,
            'best_n_count' => $data['best_n_count'] ?? null,
        ]);

        $examType->contributeRules()->delete();

        $classIds = [];

        if ($data['type'] === ExamConfigType::Supporting->value) {
            $classIds = $data['class_id'];

            foreach ($classIds as $classId) {
                $examType->contributeRules()->create([
                    'class_id' => $classId,
                    'target_exam_type_id' => $data['target_exam_type_id'],
                    'contribution_percent' => $data['contribution_percent'],
                    'session_year' => $data['session_year'],
                ]);
            }
        }

        $new = [
            'name' => $data['name'],
            'type' => $data['type'],
            'count_method' => $data['count_method'] ?? null,
            'best_n_count' => $data['best_n_count'] ?? null,
            'class_id' => $classIds ?: null,
            'class_id_label' => $classIds !== [] ? Classes::whereIn('id', $classIds)->pluck('name')->all() : null,
            'target_exam_type_id' => $data['target_exam_type_id'] ?? null,
            'target_exam_type_id_label' => isset($data['target_exam_type_id'])
                ? ExamType::find($data['target_exam_type_id'])?->name
                : null,
            'contribution_percent' => $data['contribution_percent'] ?? null,
            'session_year' => $data['session_year'] ?? null,
        ];

        activity('exam_type')
            ->performedOn($examType)
            ->event('updated')
            ->withProperties([
                'attributes' => $new,
                'old' => $old,
            ])
            ->log("Updated exam type \"{$examType->name}\".");

        return $examType;
    }
}
