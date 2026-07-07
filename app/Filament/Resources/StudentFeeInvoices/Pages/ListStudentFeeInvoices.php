<?php

namespace App\Filament\Resources\StudentFeeInvoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\StudentFeeInvoices\StudentFeeInvoiceResource;
use App\Models\Classes;
use App\Models\StudentFeeInvoice;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class ListStudentFeeInvoices extends Page
{
    protected static string $resource = StudentFeeInvoiceResource::class;

    protected string $view = 'filament.resources.student-fee-invoices.pages.list-student-fee-invoices';

    public Collection $classes;

    public function mount(): void
    {
        $this->classes = Classes::active()
            ->orderBy('order', 'asc')
            ->get()
            ->each(function ($class) {
                $stats = StudentFeeInvoice::whereHas(
                    'student', fn ($q) => $q->where('current_class_id', $class->id)
                )
                    ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(net_amount), 0) as total_amount')
                    ->groupBy('status')
                    ->get()
                    ->keyBy(fn ($item) => $item->getRawOriginal('status'));

                $class->invoiceStats = $stats;
                $class->total_invoices = $stats->sum('count');
                $class->outstanding_amount = $stats
                    ->filter(fn ($s) => in_array(
                        $s->getRawOriginal('status'),
                        [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value]
                    ))
                    ->sum('total_amount');
            });
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
