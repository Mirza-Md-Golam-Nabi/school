<?php

namespace App\Filament\Resources\LeaveApplications;

use App\Filament\Resources\LeaveApplications\Pages\CreateLeaveApplication;
use App\Filament\Resources\LeaveApplications\Pages\EditLeaveApplication;
use App\Filament\Resources\LeaveApplications\Pages\ListLeaveApplications;
use App\Filament\Resources\LeaveApplications\Schemas\LeaveApplicationForm;
use App\Filament\Resources\LeaveApplications\Tables\LeaveApplicationsTable;
use App\Models\LeaveApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LeaveApplicationResource extends Resource
{
    protected static ?string $model = LeaveApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'HR & Staff Management';

    public static function form(Schema $schema): Schema
    {
        return LeaveApplicationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeaveApplicationsTable::configure($table);
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
            'index' => ListLeaveApplications::route('/'),
            'create' => CreateLeaveApplication::route('/create'),
            'edit' => EditLeaveApplication::route('/{record}/edit'),
        ];
    }
}
