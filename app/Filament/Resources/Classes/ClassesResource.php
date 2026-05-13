<?php

namespace App\Filament\Resources\Classes;

use App\Filament\Resources\Classes\Pages\CreateClasses;
use App\Filament\Resources\Classes\Pages\EditClasses;
use App\Filament\Resources\Classes\Pages\ListClasses;
use App\Filament\Resources\Classes\RelationManagers\GroupsRelationManager;
use App\Filament\Resources\Classes\RelationManagers\SectionsRelationManager;
use App\Filament\Resources\Classes\Schemas\ClassesForm;
use App\Filament\Resources\Classes\Tables\ClassesTable;
use App\Models\Classes;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ClassesResource extends Resource
{
    protected static ?string $model = Classes::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Academic Structure';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ClassesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClassesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SectionsRelationManager::class,
            GroupsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClasses::route('/'),
            'create' => CreateClasses::route('/create'),
            'edit' => EditClasses::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
