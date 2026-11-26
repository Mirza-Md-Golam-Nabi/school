<?php

namespace App\Filament\Teacher\Resources\StudentFeeInvoices\Pages;

use App\Actions\AttachClassFeeInvoiceStatsAction;
use App\Filament\Teacher\Concerns\ScopesToClassTeacherStudents;
use App\Filament\Teacher\Resources\StudentFeeInvoices\StudentFeeInvoiceResource;
use App\Models\Classes;
use Filament\Resources\Pages\Page;

class ListStudentFeeInvoices extends Page
{
    use ScopesToClassTeacherStudents;

    protected static string $resource = StudentFeeInvoiceResource::class;

    protected string $view = 'filament.teacher.resources.student-fee-invoices.pages.list-student-fee-invoices';

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
            ->orderBy('order', 'asc')
            ->get();

        return [
            'classes' => app(AttachClassFeeInvoiceStatsAction::class)->handle($classes),
        ];
    }
}
