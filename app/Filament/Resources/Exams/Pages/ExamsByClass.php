<?php

namespace App\Filament\Resources\Exams\Pages;

use App\Filament\Resources\Exams\ExamResource;
use App\Filament\Resources\Exams\Tables\ExamsTable;
use App\Models\Classes;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

class ExamsByClass extends ListRecords
{
    protected static string $resource = ExamResource::class;

    #[Url(as: 'classId')]
    public int $classId = 0;

    public function getTitle(): string|Htmlable
    {
        return $this->resolveClass()?->name ?? 'Exams';
    }

    public function getBreadcrumbs(): array
    {
        return [
            ExamResource::getUrl() => 'Exams',
            '' => $this->getTitle(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('All Classes')
                ->icon('heroicon-o-arrow-left')
                ->url(ExamResource::getUrl())
                ->color('gray'),

            CreateAction::make()
                ->url(fn (): string => ExamResource::getUrl('create', $this->classId ? ['classId' => $this->classId] : [])),
        ];
    }

    public function table(Table $table): Table
    {
        return ExamsTable::configure(
            $table->query(
                ExamResource::getEloquentQuery()
                    ->where('class_id', $this->classId)
            )
        );
    }

    private function resolveClass(): ?Classes
    {
        return $this->classId ? Classes::find($this->classId) : null;
    }
}
