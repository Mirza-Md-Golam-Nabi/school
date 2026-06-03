<?php

namespace App\Filament\Resources\StudentFeeInvoices\Pages;

use App\Filament\Resources\StudentFeeInvoices\StudentFeeInvoiceResource;
use App\Filament\Resources\StudentFeeInvoices\Widgets\ClassInvoiceDuesSummaryWidget;
use App\Models\Classes;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ManageClassStudentFeeInvoices extends ListRecords
{
    protected static string $resource = StudentFeeInvoiceResource::class;

    #[Url(as : 'class')]
    public int $classId = 0;

    public function getTitle(): string|Htmlable
    {
        if ($this->classId) {
            return (Classes::query()->find($this->classId)?->name ?? 'Class').' — Fee Invoices';
        }

        return 'Fee Invoices';
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                'student', fn ($q) => $q->where('current_class_id', $this->classId)
            ));
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
                ->url(fn (): string => StudentFeeInvoiceResource::getUrl(
                    'create',
                    $this->classId ? ['class_id' => $this->classId] : []
                )),
        ];
    }
}
