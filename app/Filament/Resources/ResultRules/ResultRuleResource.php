<?php

namespace App\Filament\Resources\ResultRules;

use App\Filament\Resources\ResultRules\Pages\CreateResultRule;
use App\Filament\Resources\ResultRules\Pages\EditResultRule;
use App\Filament\Resources\ResultRules\Pages\ListResultRules;
use App\Filament\Resources\ResultRules\Schemas\ResultRuleForm;
use App\Filament\Resources\ResultRules\Tables\ResultRulesTable;
use App\Models\ResultRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ResultRuleResource extends Resource
{
    protected static ?string $model = ResultRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Academic Structure';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Result Rules';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ResultRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ResultRulesTable::configure($table);
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
            'index' => ListResultRules::route('/'),
            'create' => CreateResultRule::route('/create'),
            'edit' => EditResultRule::route('/{record}/edit'),
        ];
    }
}
