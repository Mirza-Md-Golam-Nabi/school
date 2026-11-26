<?php

namespace App\Filament\Resources\StudentFeeDiscounts\Pages;

use App\Actions\AttachClassFeeDiscountStatsAction;
use App\Enums\StudentStatus;
use App\Filament\Resources\StudentFeeDiscounts\StudentFeeDiscountResource;
use App\Models\Classes;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;

class ListStudentFeeDiscounts extends Page
{
    protected static string $resource = StudentFeeDiscountResource::class;

    protected string $view = 'filament.resources.student-fee-discounts.pages.list-student-fee-discounts';

    /**
     * Computed on every render instead of stored in a public property:
     * Livewire re-fetches a public Eloquent collection from its IDs on each
     * request, which would drop the attached discount stats and counts.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $classes = Classes::active()
            ->withCount([
                'studentProfiles as discounted_students_count' => fn ($q) => $q->where('status', StudentStatus::Active)
                    ->whereHas('feeDiscounts'),
                'studentProfiles as active_students_count' => fn ($q) => $q->where('status', StudentStatus::Active),
            ])
            ->orderBy('order')
            ->get();

        return [
            'classes' => app(AttachClassFeeDiscountStatsAction::class)->handle($classes),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Add Discount')
                ->url(StudentFeeDiscountResource::getUrl('create'))
                ->icon('heroicon-o-plus'),
        ];
    }
}
