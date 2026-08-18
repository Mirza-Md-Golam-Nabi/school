<?php

namespace App\Filament\Resources\LateFeeRules;

use App\Filament\Resources\LateFeeRules\Pages\CreateLateFeeRule;
use App\Filament\Resources\LateFeeRules\Pages\EditLateFeeRule;
use App\Filament\Resources\LateFeeRules\Pages\ListLateFeeRules;
use App\Filament\Resources\LateFeeRules\Schemas\LateFeeRuleForm;
use App\Filament\Resources\LateFeeRules\Tables\LateFeeRulesTable;
use App\Models\LateFeeRule;
use App\Traits\Permissions\HasEntityPermissions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LateFeeRuleResource extends Resource
{
    use HasEntityPermissions;

    protected static ?string $model = LateFeeRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Fee & Finance';

    protected static ?int $navigationSort = 8;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return LateFeeRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LateFeeRulesTable::configure($table);
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
            'index' => ListLateFeeRules::route('/'),
            'create' => CreateLateFeeRule::route('/create'),
            'edit' => EditLateFeeRule::route('/{record}/edit'),
        ];
    }
}
