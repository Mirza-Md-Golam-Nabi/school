<?php

namespace App\Filament\Resources\ActingAdmins\Schemas;

use App\Enums\UserType;
use App\Models\ActingAdmin;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ActingAdminForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Acting User')
                    ->options(ActingAdmin::activeUser())
                    ->getOptionLabelFromRecordUsing(fn (User $record) => "{$record->name} ({$record->user_type->getLabel()})")
                    ->searchable()
                    ->preload()
                    ->required(),

                DatePicker::make('from_date')
                    ->required(),

                DatePicker::make('to_date')
                    ->required()
                    ->afterOrEqual('from_date'),

                Toggle::make('is_active')
                    ->required(),

                Textarea::make('remarks')
                    ->columnSpanFull(),
            ]);
    }
}
