<?php

namespace App\Filament\Resources\SmsVendors\Tables;

use App\Filament\Resources\Concerns\ResponsiveText;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SmsVendorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('priority')
            ->columns([
                TextColumn::make('priority')
                    ->label('#')
                    ->sortable()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('name')
                    ->searchable()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('driver')
                    ->label('Provider')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('sender_id')
                    ->label('Sender ID')
                    ->copyable()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Last synced')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}
