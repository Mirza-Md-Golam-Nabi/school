<?php

namespace App\Filament\Student\Resources\FeeInvoices\Pages;

use App\Filament\Student\Pages\AcademicHistory;
use App\Filament\Student\Resources\FeeInvoices\FeeInvoiceResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class AcademicHistoryFees extends ListRecords
{
    protected static string $resource = FeeInvoiceResource::class;

    protected static bool $shouldRegisterNavigation = false;

    #[Url(as: 'year')]
    public int $year = 0;

    public function getTitle(): string|Htmlable
    {
        return "Fee Invoices — {$this->year}";
    }

    public function getBreadcrumbs(): array
    {
        return [
            AcademicHistory::getUrl() => 'Academic History',
            '' => $this->getTitle(),
        ];
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->where('year', $this->year));
    }
}
