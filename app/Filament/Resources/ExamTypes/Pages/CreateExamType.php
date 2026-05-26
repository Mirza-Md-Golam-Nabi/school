<?php

namespace App\Filament\Resources\ExamTypes\Pages;

use App\Actions\CreateExamTypeAction;
use App\Filament\Resources\ExamTypes\ExamTypeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateExamType extends CreateRecord
{
    protected static string $resource = ExamTypeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateExamTypeAction::class)->handle($data);
    }
}
