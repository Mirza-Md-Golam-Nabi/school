<?php

namespace App\Filament\Resources\StudentProfiles\Pages;

use App\Actions\CreateStudentProfileAction;
use App\Filament\Resources\StudentProfiles\Concerns\HasStudentCredentialsModal;
use App\Filament\Resources\StudentProfiles\StudentProfileResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStudentProfile extends CreateRecord
{
    use HasStudentCredentialsModal;

    protected static string $resource = StudentProfileResource::class;

    public int $filterClassId = 0;

    /**
     * @var array{email: string, password: string}|null
     */
    protected ?array $generatedStudentCredentials = null;

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

    protected function afterCreate(): void
    {
        $email = $this->record?->generated_email ?? null;
        $password = $this->record?->generated_password ?? null;

        if ($email && $password) {
            $this->generatedStudentCredentials = [
                'email' => $email,
                'password' => $password,
            ];
        }
    }

    public function createAnother(): void
    {
        parent::createAnother();

        // "Create & create another" keeps the user on this page, so the session
        // flash used by the redirecting "Create" button is never consumed by the
        // class list page — open the credentials modal here instead.
        if ($this->generatedStudentCredentials) {
            $this->mountAction('studentCredentials', $this->generatedStudentCredentials);
        }
    }

    protected function getRedirectUrl(): string
    {
        // Only the redirecting "Create" button reaches this method; the class
        // list page pulls the flashed credentials on load to show the modal.
        if ($this->generatedStudentCredentials) {
            session()->flash('generated_student_credentials', $this->generatedStudentCredentials);
        }

        return $this->getResource()::getUrl('students-by-class', ['classId' => $this->filterClassId]);
    }
}
