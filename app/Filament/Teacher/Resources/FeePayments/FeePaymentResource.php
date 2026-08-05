<?php

namespace App\Filament\Teacher\Resources\FeePayments;

use App\Filament\Teacher\Concerns\ScopesToClassTeacherStudents;
use App\Filament\Teacher\Resources\FeePayments\Pages\CreateFeePayment;
use App\Filament\Teacher\Resources\FeePayments\Pages\ListFeePayments;
use App\Filament\Teacher\Resources\FeePayments\Pages\ManageClassFeePayments;
use App\Filament\Teacher\Resources\FeePayments\Schemas\FeePaymentForm;
use App\Filament\Teacher\Resources\FeePayments\Tables\FeePaymentsTable;
use App\Models\FeePayment;
use App\Traits\Permissions\HasEntityPermissions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class FeePaymentResource extends Resource
{
    use HasEntityPermissions;
    use ScopesToClassTeacherStudents;

    protected static ?string $model = FeePayment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Fee';

    protected static ?string $navigationLabel = 'Collect Fee';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('student', fn ($query) => $query->whereIn('current_class_id', static::currentTeacherClassIds()));
    }

    public static function form(Schema $schema): Schema
    {
        return FeePaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FeePaymentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeePayments::route('/'),
            'class-payments' => ManageClassFeePayments::route('/class-payments'),
            'create' => CreateFeePayment::route('/create'),
        ];
    }
}
