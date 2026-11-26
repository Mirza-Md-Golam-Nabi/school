<?php

namespace App\Filament\Resources\Subjects\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SubjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Drag to set the order subjects appear in on marksheets and results.
            ->reorderable('sort_order')
            ->reorderRecordsTriggerAction(fn (Action $action, bool $isReordering): Action => $action
                ->button()
                ->icon(Heroicon::ArrowsUpDown)
                ->label($isReordering ? 'Done ordering' : 'Change subject order'))
            ->defaultSort('sort_order')
            ->paginated(false)
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->alignCenter(),

                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Subject Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name_bn')
                    ->label('Subject Name (Bengali)')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('parent.name')
                    ->label('Paper Of')
                    ->placeholder('—')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                IconColumn::make('has_written')
                    ->label('Written')
                    ->boolean()
                    ->alignCenter(),

                IconColumn::make('has_mcq')
                    ->label('MCQ')
                    ->boolean()
                    ->alignCenter(),

                IconColumn::make('has_practical')
                    ->label('Practical')
                    ->boolean()
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('class_group_subjects_count')
                    ->label('Assigned Classes')
                    ->counts('classGroupSubjects')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),

                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),

                TernaryFilter::make('has_practical')
                    ->label('Has Practical')
                    ->placeholder('All'),

                TernaryFilter::make('has_mcq')
                    ->label('Has MCQ')
                    ->placeholder('All'),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
