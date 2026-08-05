<?php

namespace App\Filament\Resources\StudentFeeInvoices;

use App\Filament\Resources\StudentFeeInvoices\Pages\CreateStudentFeeInvoice;
use App\Filament\Resources\StudentFeeInvoices\Pages\EditStudentFeeInvoice;
use App\Filament\Resources\StudentFeeInvoices\Pages\ListStudentFeeInvoices;
use App\Filament\Resources\StudentFeeInvoices\Pages\ManageClassStudentFeeInvoices;
use App\Filament\Resources\StudentFeeInvoices\Schemas\StudentFeeInvoiceForm;
use App\Filament\Resources\StudentFeeInvoices\Tables\StudentFeeInvoicesTable;
use App\Models\StudentFeeInvoice;
use App\Traits\Permissions\HasEntityPermissions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class StudentFeeInvoiceResource extends Resource
{
    use HasEntityPermissions;

    protected static ?string $model = StudentFeeInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Fee & Finance';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Fee Invoices';

    public static function form(Schema $schema): Schema
    {
        return StudentFeeInvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentFeeInvoicesTable::configure($table);
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
            'index' => ListStudentFeeInvoices::route('/'),
            'class-invoices' => ManageClassStudentFeeInvoices::route('/class-invoices'),
            'create' => CreateStudentFeeInvoice::route('/create'),
            'edit' => EditStudentFeeInvoice::route('/{record}/edit'),
        ];
    }
}
