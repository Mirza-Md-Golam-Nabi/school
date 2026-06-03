<?php

namespace App\Filament\Resources\FeeStructures\Pages;

use App\Filament\Resources\FeeStructures\FeeStructureResource;
use App\Models\Classes;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ManageClassFeeStructures extends ListRecords
{
    protected static string $resource = FeeStructureResource::class;

    #[Url(as: 'class')]
    public int $classId = 0;

    public function getTitle(): string|Htmlable
    {
        if ($this->classId) {
            return (Classes::query()->find($this->classId)?->name ?? 'Class').' — Fee Structures';
        }

        return 'Fee Structures';
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
                ->url(FeeStructureResource::getUrl('index'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray'),
            CreateAction::make()
                ->url(fn (): string => FeeStructureResource::getUrl(
                    'create',
                    $this->classId ? ['class_id' => $this->classId] : []
                )),
        ];
    }
}
