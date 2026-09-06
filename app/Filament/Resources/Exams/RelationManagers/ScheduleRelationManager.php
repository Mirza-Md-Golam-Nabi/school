<?php

namespace App\Filament\Resources\Exams\RelationManagers;

use App\Models\Subject;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class ScheduleRelationManager extends RelationManager
{
    protected static string $relationship = 'schedules';

    protected static ?string $title = 'Exam Schedule';

    public function form(Schema $schema): Schema
    {
        $exam = $this->getOwnerRecord();
        $classId = $exam->class_id;

        return $schema
            ->columns(2)
            ->components([
                Select::make('subject_id')
                    ->label('Subject')
                    ->options(fn () => Subject::whereHas(
                        'classes',
                        fn (Builder $q) => $q->where('classes.id', $classId)
                    )->pluck('name', 'id'))
                    ->searchable()
                    ->native(false)
                    ->required()
                    ->unique(
                        table: 'exam_schedules',
                        column: 'subject_id',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule) => $rule->where('exam_id', $exam->id),
                    ),

                DatePicker::make('exam_date')
                    ->label('Exam Date')
                    ->native(false)
                    ->required()
                    ->minDate($exam->start_date)
                    ->maxDate($exam->end_date)
                    ->helperText("এই exam-এর সময়সীমা: {$exam->start_date?->format('d M Y')} থেকে {$exam->end_date?->format('d M Y')}"),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject_id')
            ->defaultSort('exam_date')
            ->columns([
                TextColumn::make('subject.name')
                    ->label('Subject')
                    ->searchable(),

                TextColumn::make('exam_date')
                    ->label('Date')
                    ->date('d M Y (D)')
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('downloadSchedule')
                    ->label('Download Schedule (PDF)')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('info')
                    ->visible(fn (): bool => $this->getOwnerRecord()->schedules()->exists())
                    ->schema([
                        Section::make('Page Size')
                            ->schema([
                                Radio::make('pageSize')
                                    ->hiddenLabel()
                                    ->options([
                                        'A5' => 'A5',
                                        'A4' => 'A4',
                                    ])
                                    ->default('A5')
                                    ->inline()
                                    ->inlineLabel(false)
                                    ->required(),
                            ]),
                    ])
                    ->modalHeading('Download Schedule (PDF)')
                    ->modalSubmitActionLabel('Download')
                    ->action(fn (array $data) => $this->redirect(route('exams.schedule.download', [
                        'exam' => $this->getOwnerRecord(),
                        'pageSize' => $data['pageSize'] ?? 'A5',
                    ]))),

                CreateAction::make()
                    ->modalWidth(Width::Medium),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
