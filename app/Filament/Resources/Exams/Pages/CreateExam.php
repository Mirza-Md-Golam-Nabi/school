<?php

namespace App\Filament\Resources\Exams\Pages;

use App\Actions\CreateExamAction;
use App\Filament\Resources\Exams\ExamResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateExam extends CreateRecord
{
    protected static string $resource = ExamResource::class;

    public int $filterClassId = 0;

    public function mount(): void
    {
        $this->filterClassId = request()->integer('classId');
        parent::mount();
    }

    protected function fillForm(): void
    {
        parent::fillForm();

        if ($this->filterClassId) {
            $this->data['class_id'] = [$this->filterClassId];
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        $classIds = (array) $data['class_id'];
        $firstExam = null;

        foreach ($classIds as $classId) {
            $exam = app(CreateExamAction::class)->handle([...$data, 'class_id' => $classId]);
            $firstExam ??= $exam;
        }

        return $firstExam;
    }

    protected function getRedirectUrl(): string
    {
        return $this->filterClassId
            ? ExamResource::getUrl('exams-by-class', ['classId' => $this->filterClassId])
            : $this->getResource()::getUrl('index');
    }
}
