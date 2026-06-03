<?php

namespace App\Filament\Resources\FeeStructures\Pages;

use App\Filament\Resources\FeeStructures\FeeStructureResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFeeStructure extends CreateRecord
{
    protected static string $resource = FeeStructureResource::class;

    public int $filterClassId = 0;

    public function mount(): void
    {
        $this->filterClassId = request()->integer('class_id');
        parent::mount();
    }

    protected function fillForm(): void
    {
        parent::fillForm();

        if ($this->filterClassId) {
            $this->data['class_id'] = $this->filterClassId;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
