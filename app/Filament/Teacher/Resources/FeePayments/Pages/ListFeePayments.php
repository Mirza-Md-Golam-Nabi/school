<?php

namespace App\Filament\Teacher\Resources\FeePayments\Pages;

use App\Actions\AttachClassFeePaymentStatsAction;
use App\Filament\Teacher\Concerns\ScopesToClassTeacherStudents;
use App\Filament\Teacher\Resources\FeePayments\FeePaymentResource;
use App\Models\Classes;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;

class ListFeePayments extends Page
{
    use ScopesToClassTeacherStudents;

    protected static string $resource = FeePaymentResource::class;

    protected string $view = 'filament.teacher.resources.fee-payments.pages.list-fee-payments';

    /**
     * Computed on every render instead of stored in a public property:
     * Livewire re-fetches a public Eloquent collection from its IDs on
     * each request, which would drop the attached card stats.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $classes = Classes::active()
            ->whereIn('id', static::currentTeacherClassIds())
            ->orderBy('order')
            ->get();

        return [
            'classes' => app(AttachClassFeePaymentStatsAction::class)->handle($classes),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Collect Fee')
                ->url(FeePaymentResource::getUrl('create'))
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => FeePaymentResource::canCreate()),
        ];
    }
}
