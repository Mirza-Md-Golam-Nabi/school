<?php

namespace App\Filament\Resources\StudentFeeInvoices\Pages;

use App\Actions\AttachClassFeeInvoiceStatsAction;
use App\Filament\Resources\StudentFeeInvoices\StudentFeeInvoiceResource;
use App\Filament\Resources\StudentFeeInvoices\Widgets\FeeReconciliationWidget;
use App\Models\Classes;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;

class ListStudentFeeInvoices extends Page
{
    protected static string $resource = StudentFeeInvoiceResource::class;

    protected string $view = 'filament.resources.student-fee-invoices.pages.list-student-fee-invoices';

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
            ->orderBy('order', 'asc')
            ->get();

        return [
            'classes' => app(AttachClassFeeInvoiceStatsAction::class)->handle($classes),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FeeReconciliationWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('New Invoice')
                ->url(StudentFeeInvoiceResource::getUrl('create'))
                ->icon('heroicon-o-plus'),
        ];
    }
}
