<?php

namespace App\Filament\Resources\StudentFeeDiscounts\Pages;

use App\Filament\Resources\StudentFeeDiscounts\StudentFeeDiscountResource;
use App\Models\Classes;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ManageClassStudentFeeDiscounts extends ListRecords
{
    protected static string $resource = StudentFeeDiscountResource::class;

    #[Url(as: 'class')]
    public int $classId = 0;

    public function getTitle(): string|Htmlable
    {
        if ($this->classId) {
            return (Classes::query()->find($this->classId)?->name ?? 'Class').' — Student Discounts';
        }

        return 'Student Discounts';
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                'student', fn ($q) => $q->where('current_class_id', $this->classId)
            ));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('All Classes')
                ->url(StudentFeeDiscountResource::getUrl('index'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray'),
            CreateAction::make()
                ->url(fn (): string => StudentFeeDiscountResource::getUrl(
                    'create',
                    $this->classId ? ['class_id' => $this->classId] : []
                )),
        ];
    }
}
