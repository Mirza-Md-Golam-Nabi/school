<?php

namespace App\Filament\Resources\StudentProfiles\Pages;

use App\Actions\CreateStudentProfileAction;
use App\Filament\Resources\StudentProfiles\StudentProfileResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStudentProfile extends CreateRecord
{
    protected static string $resource = StudentProfileResource::class;

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
            $this->data['current_class_id'] = $this->filterClassId;
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateStudentProfileAction::class)->handle($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
