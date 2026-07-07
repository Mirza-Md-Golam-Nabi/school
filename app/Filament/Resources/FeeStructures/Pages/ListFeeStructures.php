<?php

namespace App\Filament\Resources\FeeStructures\Pages;

use App\Filament\Resources\FeeStructures\FeeStructureResource;
use App\Models\Classes;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class ListFeeStructures extends Page
{
    protected static string $resource = FeeStructureResource::class;

    protected string $view = 'filament.resources.fee-structures.pages.list-fee-structures';

    public Collection $classes;

    public function mount(): void
    {
        $this->classes = Classes::with([
            'feeStructures' => fn ($q) => $q->where('is_active', true)->with('feeType'),
        ])
            ->withCount(['feeStructures' => fn ($q) => $q->where('is_active', true)])
            ->active()
            ->orderBy('order')
            ->get()
            ->each(function ($class) {
                $class->setRelation(
                    'feeStructures',
                    $class->feeStructures->sortBy('feeType.name')->values()
                );
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Add Fee Structure')
                ->url(FeeStructureResource::getUrl('create'))
                ->icon('heroicon-o-plus'),
        ];
    }
}
