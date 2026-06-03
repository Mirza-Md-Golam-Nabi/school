<?php

namespace App\Filament\Resources\FeePayments;

use App\Filament\Resources\FeePayments\Pages\CreateFeePayment;
use App\Filament\Resources\FeePayments\Pages\EditFeePayment;
use App\Filament\Resources\FeePayments\Pages\ListFeePayments;
use App\Filament\Resources\FeePayments\Pages\ManageClassFeePayments;
use App\Filament\Resources\FeePayments\Schemas\FeePaymentForm;
use App\Filament\Resources\FeePayments\Tables\FeePaymentsTable;
use App\Models\FeePayment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FeePaymentResource extends Resource
{
    protected static ?string $model = FeePayment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Fee & Finance';

    protected static ?string $navigationLabel = 'Fee Payments';

    public static function form(Schema $schema): Schema
    {
        return FeePaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FeePaymentsTable::configure($table);
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
            'index' => ListFeePayments::route('/'),
            'class-payments' => ManageClassFeePayments::route('/class-payments'),
            'create' => CreateFeePayment::route('/create'),
            'edit' => EditFeePayment::route('/{record}/edit'),
        ];
    }
}
