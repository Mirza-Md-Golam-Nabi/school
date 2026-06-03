<?php

namespace App\Filament\Resources\StudentFeeDiscounts\Pages;

use App\Enums\StudentStatus;
use App\Filament\Resources\StudentFeeDiscounts\StudentFeeDiscountResource;
use App\Models\Classes;
use App\Models\StudentFeeDiscount;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class ListStudentFeeDiscounts extends Page
{
    protected static string $resource = StudentFeeDiscountResource::class;

    protected string $view = 'filament.resources.student-fee-discounts.pages.list-student-fee-discounts';

    public Collection $classes;

    public function mount(): void
    {
        $this->classes = Classes::where('is_active', true)
            ->withCount([
                'studentProfiles as discounted_students_count' => fn ($q) => $q->where('status', StudentStatus::Active)
                    ->whereHas('feeDiscounts'),
                'studentProfiles as active_students_count' => fn ($q) => $q->where('status', StudentStatus::Active),
            ])
            ->orderBy('order')
            ->get()
            ->each(function ($class) {
                $class->discountStats = StudentFeeDiscount::whereHas(
                    'student', fn ($q) => $q->where('current_class_id', $class->id)
                        ->where('status', StudentStatus::Active)
                )
                    ->with('discount')
                    ->get()
                    ->groupBy('discount_id')
                    ->map(fn ($group) => (object) [
                        'name' => $group->first()->discount->name,
                        'student_count' => $group->unique('student_id')->count(),
                        'color' => $group->first()->discount->discount_type->getColor(),
                    ])
                    ->sortByDesc('name')
                    ->values();
            });
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
