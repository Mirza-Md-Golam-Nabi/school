<?php

namespace App\Filament\Resources\Exams\Pages;

use App\Filament\Resources\Exams\ExamResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditExam extends EditRecord
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update([
            'exam_type_id' => $data['exam_type_id'],
            'class_id' => $data['class_id'],
            'session_year' => $data['session_year'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_published' => $data['is_published'] ?? false,
        ]);

        return $record;
    }
}
