<?php

namespace App\Filament\Resources\ResultRules\Tables;

use App\Models\ResultRule;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ResultRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['targetExamType', 'classes', 'components.examType']))
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('targetExamType.name')
                    ->label('Result Of'),
                TextColumn::make('classes.name')
                    ->label('Classes')
                    ->badge(),
                TextColumn::make('components')
                    ->label('Made From')
                    ->state(fn (ResultRule $record): string => $record->componentsSummary())
                    ->wrap(),
                TextColumn::make('full_marks')
                    ->label('Full Marks')
                    ->state(fn (ResultRule $record): float => $record->resolvedFullMarks()),
                TextColumn::make('rounding')
                    ->badge()
                    ->formatStateUsing(fn (ResultRule $record): string => $record->rounding->name)
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton(),
            ]);
    }
}
