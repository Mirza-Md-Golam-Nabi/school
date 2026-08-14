<?php

namespace App\Filament\Resources\StudentProfiles\Pages;

use App\Filament\Resources\StudentProfiles\Concerns\HasStudentCredentialsModal;
use App\Filament\Resources\StudentProfiles\StudentProfileResource;
use App\Filament\Resources\StudentProfiles\Tables\StudentProfilesTable;
use App\Models\Classes;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

class StudentsByClass extends ListRecords
{
    use HasStudentCredentialsModal;

    protected static string $resource = StudentProfileResource::class;

    #[Url(as: 'classId')]
    public int $classId = 0;

    public function mount(): void
    {
        parent::mount();

        $credentials = session()->pull('generated_student_credentials');

        if ($credentials) {
            // Actions aren't cached yet during mount(), so mountAction() here would
            // silently no-op. defaultAction/defaultActionArguments are read by
            // Filament's page view and mounted client-side via wire:init, once the
            // component has fully booted.
            $this->defaultAction = 'studentCredentials';
            $this->defaultActionArguments = $credentials;
        }
    }

    public function getTitle(): string|Htmlable
    {
        return $this->resolveClass()?->name ?? 'Students';
    }

    public function getBreadcrumbs(): array
    {
        return [
            StudentProfileResource::getUrl() => 'Students',
            '' => $this->getTitle(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('All Classes')
                ->icon('heroicon-o-arrow-left')
                ->url(StudentProfileResource::getUrl())
                ->color('gray'),

            Action::make('promote')
                ->label('Promote')
                ->icon('heroicon-o-arrow-trending-up')
                ->color('success')
                ->url(StudentProfileResource::getUrl('promote-students', ['classId' => $this->classId])),

            CreateAction::make()
                ->url(fn (): string => StudentProfileResource::getUrl('create', $this->classId ? ['classId' => $this->classId] : [])),
        ];
    }

    public function table(Table $table): Table
    {
        return StudentProfilesTable::configure(
            $table->query(
                StudentProfileResource::getEloquentQuery()
                    ->where('current_class_id', $this->classId)
            )
        );
    }

    private function resolveClass(): ?Classes
    {
        return $this->classId ? Classes::find($this->classId) : null;
    }
}
