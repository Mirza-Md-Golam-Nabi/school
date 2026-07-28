<?php

namespace App\Filament\Resources\SalaryInvoices;

use App\Filament\Resources\SalaryInvoices\Pages\ListSalaryInvoices;
use App\Filament\Resources\SalaryInvoices\Tables\SalaryInvoicesTable;
use App\Models\SalaryInvoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SalaryInvoiceResource extends Resource
{
    protected static ?string $model = SalaryInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Salary Management';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Salary Invoices';

    public static function table(Table $table): Table
    {
        return SalaryInvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalaryInvoices::route('/'),
        ];
    }
}
