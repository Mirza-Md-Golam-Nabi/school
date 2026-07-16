<?php

namespace App\Filament\Resources\SchoolAccounts\Schemas;

use App\Models\FeeType;
use App\Models\SchoolAccount;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SchoolAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('e.g. Main Fund, Exam Fund')
                    ->extraAttributes(['class' => FeeTypeResyncPolicyFields::RESPONSIVE_TEXT]),
                TextInput::make('current_balance')
                    ->label('Opening Balance')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix('৳')
                    ->default(0)
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated(fn (string $operation): bool => $operation === 'create')
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? 'Balance is now managed automatically from fee payments.'
                        : null)
                    ->extraAttributes(['class' => FeeTypeResyncPolicyFields::RESPONSIVE_TEXT]),
                Select::make('fee_type_ids')
                    ->label('Fee Types (ফি টাইপ)')
                    ->multiple()
                    ->live()
                    ->options(fn (?SchoolAccount $record) => FeeType::query()
                        ->where(fn ($query) => $query
                            ->whereNull('school_account_id')
                            ->when($record, fn ($query) => $query->orWhere('school_account_id', $record->id)))
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->helperText('Payments collected for the selected fee types will automatically post into this fund. Fee types already linked to another fund are not shown here — use the "+" action on the accounts list to move one.')
                    ->extraAttributes(['class' => FeeTypeResyncPolicyFields::RESPONSIVE_TEXT]),
                ...FeeTypeResyncPolicyFields::make('fee_type_ids'),
            ]);
    }
}
