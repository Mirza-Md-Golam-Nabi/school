<?php

namespace App\Filament\Teacher\Resources\StudentFeeInvoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Teacher\Concerns\ScopesToClassTeacherStudents;
use App\Filament\Teacher\Resources\StudentFeeInvoices\StudentFeeInvoiceResource;
use App\Models\Classes;
use App\Models\StudentFeeInvoice;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class ListStudentFeeInvoices extends Page
{
    use ScopesToClassTeacherStudents;

    protected static string $resource = StudentFeeInvoiceResource::class;

    protected string $view = 'filament.teacher.resources.student-fee-invoices.pages.list-student-fee-invoices';

    public Collection $classes;

    public function mount(): void
    {
        $this->classes = Classes::active()
            ->whereIn('id', static::currentTeacherClassIds())
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
}
