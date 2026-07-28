<?php

namespace App\Filament\Resources\SalaryStructures\Tables;

use App\Filament\Resources\Concerns\ResponsiveText;
use App\Models\SalaryStructure;
use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SalaryStructuresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('profileable.user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('profileable_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => $state === TeacherProfile::class ? 'Teacher' : 'Staff')
                    ->badge(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->state(fn (SalaryStructure $record): float => $record->use_components
                        ? (float) $record->components()->sum('amount')
                        : (float) $record->flat_amount)
                    ->money('BDT')
                    ->alignEnd()
                    ->weight('semibold')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                IconColumn::make('use_components')
                    ->label('Component-wise')
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('effective_from')
                    ->date()
                    ->sortable()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('effective_to')
                    ->label('Effective To')
                    ->date()
                    ->placeholder('চলমান')
                    ->sortable()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
            ])
            ->filters([
                SelectFilter::make('profileable_type')
                    ->label('Type')
                    ->options([
                        TeacherProfile::class => 'Teacher',
                        StaffProfile::class => 'Staff',
                    ]),
                TernaryFilter::make('use_components')
                    ->label('Component-wise'),
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
