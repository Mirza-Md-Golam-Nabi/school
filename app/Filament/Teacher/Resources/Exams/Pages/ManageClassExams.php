<?php

namespace App\Filament\Teacher\Resources\Exams\Pages;

use App\Filament\Teacher\Concerns\ScopesToTaughtClasses;
use App\Filament\Teacher\Resources\Exams\ExamResource;
use App\Models\Classes;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ManageClassExams extends ListRecords
{
    use ScopesToTaughtClasses;

    protected static string $resource = ExamResource::class;

    #[Url(as: 'class')]
    public int $classId = 0;

    public function mount(): void
    {
        parent::mount();

        abort_unless(in_array($this->classId, static::currentTeacherTaughtClassIds()), 403);
    }

    public function getTitle(): string|Htmlable
    {
        if ($this->classId) {
            return (Classes::query()->find($this->classId)?->name ?? 'Class').' — Exams';
        }

        return 'Exams';
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->where('class_id', $this->classId));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('All Classes')
                ->url(ExamResource::getUrl('index'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray'),
        ];
    }
}
