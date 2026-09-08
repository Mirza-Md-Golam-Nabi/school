<?php

namespace App\Filament\Resources\StudentProfiles\Pages;

use App\Enums\StudentListColumn;
use App\Filament\Resources\StudentProfiles\Concerns\HasStudentCredentialsModal;
use App\Filament\Resources\StudentProfiles\StudentProfileResource;
use App\Filament\Resources\StudentProfiles\Tables\StudentProfilesTable;
use App\Filament\Resources\StudentProfiles\Widgets\GroupStudentCountsWidget;
use App\Models\Classes;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\GridDirection;
use Filament\Support\Icons\Heroicon;
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

    protected function getHeaderWidgets(): array
    {
        if (! $this->resolveClass()?->has_group) {
            return [];
        }

        return [
            GroupStudentCountsWidget::make(['classId' => $this->classId]),
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

            CreateAction::make()
                ->url(fn (): string => StudentProfileResource::getUrl('create', $this->classId ? ['classId' => $this->classId] : [])),

            Action::make('downloadStudentList')
                ->label('Student List (PDF)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('info')
                ->visible(fn (): bool => $this->classId > 0)
                ->schema([
                    Section::make('Columns')
                        ->schema([
                            CheckboxList::make('columns')
                                ->hiddenLabel()
                                ->options(StudentListColumn::options())
                                ->default(StudentListColumn::defaults())
                                ->columns(['default' => 2, 'md' => 3, 'lg' => 4])
                                ->gridDirection(GridDirection::Row)
                                ->bulkToggleable(),
                        ]),
                    Section::make('Orientation')
                        ->schema([
                            Radio::make('orientation')
                                ->hiddenLabel()
                                ->options([
                                    'P' => 'Portrait',
                                    'L' => 'Landscape',
                                ])
                                ->default('P')
                                ->inline()
                                ->inlineLabel(false)
                                ->required(),
                        ]),
                ])
                ->modalHeading('Download Student List (PDF)')
                ->modalSubmitActionLabel('Download')
                ->action(fn (array $data) => $this->redirect(route('student-list.class.download', [
                    'class' => $this->classId,
                    'columns' => $data['columns'] ?? [],
                    'orientation' => $data['orientation'] ?? 'P',
                ]))),
        ];
    }

    public function table(Table $table): Table
    {
        $query = StudentProfileResource::getEloquentQuery()
            ->where('current_class_id', $this->classId)
            ->where('session_year', now()->year);

        return StudentProfilesTable::configure($table->query($query), $this->resolveClass());
    }

    private function resolveClass(): ?Classes
    {
        return $this->classId ? Classes::find($this->classId) : null;
    }
}
