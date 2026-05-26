<?php

namespace App\Filament\Resources\ExamTypes\Pages;

use App\Actions\UpdateExamTypeAction;
use App\Enums\ExamConfigType;
use App\Filament\Resources\ExamTypes\ExamTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditExamType extends EditRecord
{
    protected static string $resource = ExamTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $examType = $this->getRecord()->load('examTypeConfig', 'contributeRules');

        $config = $examType->examTypeConfig;

        $data['type'] = $config?->type?->value;
        $data['count_method'] = $config?->count_method?->value;
        $data['best_n_count'] = $config?->best_n_count;

        if ($config?->type === ExamConfigType::Supporting) {
            $rules = $examType->contributeRules;
            $firstRule = $rules->first();

            $data['class_id'] = $rules->pluck('class_id')->toArray();
            $data['target_exam_type_id'] = $firstRule?->target_exam_type_id;
            $data['contribution_percent'] = $firstRule?->contribution_percent;
            $data['session_year'] = $firstRule?->session_year;
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateExamTypeAction::class)->handle($record, $data);
    }
}
