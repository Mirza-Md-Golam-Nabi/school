<?php

namespace App\Filament\Resources\SmsVendors\Schemas;

use App\Enums\SmsVendorDriver;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SmsVendorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Vendor')
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100),
                        Select::make('driver')
                            ->label('Provider')
                            ->options(SmsVendorDriver::class)
                            ->default(SmsVendorDriver::BulkSmsBd->value)
                            ->required()
                            ->native(false),
                        TextInput::make('sender_id')
                            ->label('Sender ID')
                            ->required()
                            ->maxLength(20)
                            ->helperText('যেমন: 8809617613451'),
                        TextInput::make('api_key')
                            ->label('API Key')
                            ->password()
                            ->autocomplete('new-password')
                            ->maxLength(255)
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->formatStateUsing(fn (): ?string => null)
                            ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'আগের key রাখতে ফাঁকা রাখুন — key কখনো দেখানো হয় না।' : null),
                        TextInput::make('priority')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(99)
                            ->default(1)
                            ->required()
                            ->helperText('ছোট সংখ্যা আগে চেষ্টা হবে; ব্যর্থ হলে পরেরটা।'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }
}
