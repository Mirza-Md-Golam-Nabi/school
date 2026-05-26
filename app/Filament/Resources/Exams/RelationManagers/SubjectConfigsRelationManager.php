<?php

namespace App\Filament\Resources\Exams\RelationManagers;

use App\Models\Subject;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SubjectConfigsRelationManager extends RelationManager
{
    protected static string $relationship = 'subjectConfigs';

    public function form(Schema $schema): Schema
    {
        $classId = $this->getOwnerRecord()->class_id;

        $subjectOptions = Subject::whereHas(
            'classes',
            fn (Builder $q) => $q->where('classes.id', $classId)
        )->pluck('name', 'id')->toArray();

        return $schema
            ->columns(1)
            ->components([
                Select::make('subject_id')
                    ->label('Subject')
                    ->options($subjectOptions)
                    ->searchable()
                    ->live()
                    ->required(),

                Section::make('MCQ')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('check_mcq_pass')
                                    ->label('MCQ আলাদা pass করতে হবে?')
                                    ->default(false)
                                    ->live()
                                    ->columnSpanFull(),

                                TextInput::make('mcq_total')
                                    ->label('MCQ Total')
                                    ->numeric()
                                    ->minValue(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculateTotalMarks($get, $set))
                                    ->required(),

                                TextInput::make('mcq_pass_mark')
                                    ->label('MCQ Pass Mark')
                                    ->numeric()
                                    ->minValue(1)
                                    ->visible(fn (Get $get): bool => (bool) $get('check_mcq_pass'))
                                    ->required(fn (Get $get): bool => (bool) $get('check_mcq_pass')),
                            ]),
                    ])
                    ->visible(fn (Get $get): bool => self::subjectHas($get, 'has_mcq')),

                Section::make('Written')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('check_written_pass')
                                    ->label('Written আলাদা pass করতে হবে?')
                                    ->default(false)
                                    ->live()
                                    ->columnSpanFull(),

                                TextInput::make('written_total')
                                    ->label('Written Total')
                                    ->numeric()
                                    ->minValue(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculateTotalMarks($get, $set))
                                    ->required(),

                                TextInput::make('written_pass_mark')
                                    ->label('Written Pass Mark')
                                    ->numeric()
                                    ->minValue(1)
                                    ->visible(fn (Get $get): bool => (bool) $get('check_written_pass'))
                                    ->required(fn (Get $get): bool => (bool) $get('check_written_pass')),
                            ]),
                    ])
                    ->visible(fn (Get $get): bool => self::subjectHas($get, 'has_written')),

                Section::make('Practical')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('check_practical_pass')
                                    ->label('Practical আলাদা pass করতে হবে?')
                                    ->default(false)
                                    ->live()
                                    ->columnSpanFull(),

                                TextInput::make('practical_total')
                                    ->label('Practical Total')
                                    ->numeric()
                                    ->minValue(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculateTotalMarks($get, $set))
                                    ->required(),

                                TextInput::make('practical_pass_mark')
                                    ->label('Practical Pass Mark')
                                    ->numeric()
                                    ->minValue(1)
                                    ->visible(fn (Get $get): bool => (bool) $get('check_practical_pass'))
                                    ->required(fn (Get $get): bool => (bool) $get('check_practical_pass')),
                            ]),
                    ])
                    ->visible(fn (Get $get): bool => self::subjectHas($get, 'has_practical')),

                Grid::make(2)
                    ->schema([
                        TextInput::make('total_marks')
                            ->label('Total Marks')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('MCQ + Written + Practical স্বয়ংক্রিয়ভাবে হিসাব হয়')
                            ->required(),

                        TextInput::make('pass_mark')
                            ->label('Pass Mark')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject_id')
            ->columns([
                TextColumn::make('subject.name')
                    ->label('Subject')
                    ->searchable(),

                TextColumn::make('total_marks')
                    ->label('Total')
                    ->alignCenter(),

                TextColumn::make('pass_mark')
                    ->label('Pass')
                    ->alignCenter(),

                TextColumn::make('mcq_total')
                    ->label('MCQ')
                    ->alignCenter()
                    ->placeholder('—'),

                TextColumn::make('written_total')
                    ->label('Written')
                    ->alignCenter()
                    ->placeholder('—'),

                TextColumn::make('practical_total')
                    ->label('Practical')
                    ->alignCenter()
                    ->placeholder('—'),

                IconColumn::make('check_mcq_pass')
                    ->label('MCQ Pass?')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('check_written_pass')
                    ->label('Written Pass?')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('check_practical_pass')
                    ->label('Practical Pass?')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalWidth(Width::Large)
                    ->createAnother(false),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth(Width::Large)
                    ->iconButton(),
                RestoreAction::make()
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton(),
                ForceDeleteAction::make()
                    ->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(
                fn (Builder $query) => $query->withoutGlobalScopes([SoftDeletingScope::class])
            );
    }

    private static function subjectHas(Get $get, string $attribute): bool
    {
        $subjectId = $get('subject_id');

        if (! $subjectId) {
            return false;
        }

        return (bool) Subject::find($subjectId)?->{$attribute};
    }

    private static function recalculateTotalMarks(Get $get, Set $set): void
    {
        $total = (int) ($get('mcq_total') ?? 0)
            + (int) ($get('written_total') ?? 0)
            + (int) ($get('practical_total') ?? 0);

        if ($total > 0) {
            $set('total_marks', $total);
        }
    }
}
