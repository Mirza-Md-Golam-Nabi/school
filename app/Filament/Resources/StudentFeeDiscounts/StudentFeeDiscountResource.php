<?php

namespace App\Filament\Resources\StudentFeeDiscounts;

use App\Filament\Resources\StudentFeeDiscounts\Pages\CreateStudentFeeDiscount;
use App\Filament\Resources\StudentFeeDiscounts\Pages\EditStudentFeeDiscount;
use App\Filament\Resources\StudentFeeDiscounts\Pages\ListStudentFeeDiscounts;
use App\Filament\Resources\StudentFeeDiscounts\Pages\ManageClassStudentFeeDiscounts;
use App\Filament\Resources\StudentFeeDiscounts\Schemas\StudentFeeDiscountForm;
use App\Filament\Resources\StudentFeeDiscounts\Tables\StudentFeeDiscountsTable;
use App\Models\StudentFeeDiscount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class StudentFeeDiscountResource extends Resource
{
    protected static ?string $model = StudentFeeDiscount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Fee & Finance';

    protected static ?string $navigationLabel = 'Student Discounts';

    public static function form(Schema $schema): Schema
    {
        return StudentFeeDiscountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentFeeDiscountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudentFeeDiscounts::route('/'),
            'class-discounts' => ManageClassStudentFeeDiscounts::route('/class-discounts'),
            'create' => CreateStudentFeeDiscount::route('/create'),
            'edit' => EditStudentFeeDiscount::route('/{record}/edit'),
        ];
    }
}
