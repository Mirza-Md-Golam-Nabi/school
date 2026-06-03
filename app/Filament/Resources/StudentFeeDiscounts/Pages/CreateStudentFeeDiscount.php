<?php

namespace App\Filament\Resources\StudentFeeDiscounts\Pages;

use App\Filament\Resources\StudentFeeDiscounts\StudentFeeDiscountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStudentFeeDiscount extends CreateRecord
{
    protected static string $resource = StudentFeeDiscountResource::class;

    public int $filterClassId = 0;

    public function mount(): void
    {
        // Store class_id BEFORE parent::mount() so fillForm() override finds it
        $this->filterClassId = request()->integer('class_id');
        parent::mount();
    }

    protected function fillForm(): void
    {
        parent::fillForm();

        // Re-apply class filter on every fill (initial load + "create another" reset)
        if ($this->filterClassId) {
            $this->data['class_id_filter'] = $this->filterClassId;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
