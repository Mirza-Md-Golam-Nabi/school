<?php

namespace App\Filament\Teacher\Resources\StudentFeeInvoices\Pages;

use App\Filament\Resources\StudentFeeInvoices\Tables\ClassInvoicesByStudentTable;
use App\Filament\Resources\StudentFeeInvoices\Widgets\ClassInvoiceDuesSummaryWidget;
use App\Filament\Teacher\Concerns\ScopesToClassTeacherStudents;
use App\Filament\Teacher\Resources\StudentFeeInvoices\StudentFeeInvoiceResource;
use App\Models\Classes;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

class ManageClassStudentFeeInvoices extends ListRecords
{
    use ScopesToClassTeacherStudents;

    protected static string $resource = StudentFeeInvoiceResource::class;

    #[Url(as: 'class')]
    public int $classId = 0;

    public function mount(): void
    {
        parent::mount();

        // ClassInvoicesByStudentTable queries StudentProfile directly by $classId,
        // bypassing the resource's own getEloquentQuery() scoping — enforce it here.
        abort_unless(in_array($this->classId, static::currentTeacherClassIds()), 403);
    }

    public function getTitle(): string|Htmlable
    {
        if ($this->classId) {
            return (Classes::query()->find($this->classId)?->name ?? 'Class').' — Fee Invoices';
        }

        return 'Fee Invoices';
    }

    public function table(Table $table): Table
    {
        return ClassInvoicesByStudentTable::configure($table, $this->classId);
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ClassInvoiceDuesSummaryWidget::make(['classId' => $this->classId]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('All Classes')
                ->url(StudentFeeInvoiceResource::getUrl('index'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray'),

            CreateAction::make()
                ->url(fn (): string => StudentFeeInvoiceResource::getUrl('create'))
                ->visible(fn (): bool => StudentFeeInvoiceResource::canCreate()),
        ];
    }
}
