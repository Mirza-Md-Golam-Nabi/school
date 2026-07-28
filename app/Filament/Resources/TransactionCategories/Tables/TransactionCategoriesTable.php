<?php

namespace App\Filament\Resources\TransactionCategories\Tables;

use App\Enums\TransactionType;
use App\Filament\Resources\Concerns\ResponsiveText;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TransactionCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(TransactionType::class),
                TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
