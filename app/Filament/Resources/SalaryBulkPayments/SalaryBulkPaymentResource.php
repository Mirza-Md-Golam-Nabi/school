<?php

namespace App\Filament\Resources\SalaryBulkPayments;

use App\Filament\Resources\SalaryBulkPayments\Pages\ListSalaryBulkPayments;
use App\Filament\Resources\SalaryBulkPayments\Tables\SalaryBulkPaymentsTable;
use App\Models\SalaryBulkPayment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SalaryBulkPaymentResource extends Resource
{
    protected static ?string $model = SalaryBulkPayment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Salary Management';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Bulk Payments';

    public static function table(Table $table): Table
    {
        return SalaryBulkPaymentsTable::configure($table);
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
            'index' => ListSalaryBulkPayments::route('/'),
        ];
    }
}
