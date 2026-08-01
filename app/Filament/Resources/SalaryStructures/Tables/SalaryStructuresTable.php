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
use Illuminate\Database\Eloquent\Builder;

class SalaryStructuresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query, string $direction): Builder => self::orderByProfileableName($query, $direction))
            ->columns([
                TextColumn::make('profileable.user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => self::orderByProfileableName($query, $direction))
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

    /**
     * `profileable` is a MorphTo relation, which Filament's built-in relationship
     * sorting doesn't support (only BelongsTo/HasOne/MorphOne/*Through chains are
     * allowed) — sorting by `profileable.user.name` directly would throw. Ordering
     * by a correlated subquery per possible profileable type sidesteps that.
     */
    private static function orderByProfileableName(Builder $query, string $direction): Builder
    {
        $table = $query->getModel()->getTable();
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $query->orderByRaw(
            "(CASE {$table}.profileable_type
                WHEN ? THEN (SELECT users.name FROM teacher_profiles INNER JOIN users ON users.id = teacher_profiles.user_id WHERE teacher_profiles.id = {$table}.profileable_id)
                WHEN ? THEN (SELECT users.name FROM staff_profiles INNER JOIN users ON users.id = staff_profiles.user_id WHERE staff_profiles.id = {$table}.profileable_id)
            END) {$direction}",
            [TeacherProfile::class, StaffProfile::class]
        );
    }
}
