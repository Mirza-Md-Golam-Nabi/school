<?php

namespace App\Filament\Resources\Exams\RelationManagers;

use App\Enums\ClassLevel;
use App\Models\GradeScale;
use App\Models\StudentMeritRanking;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TabulationSheetRelationManager extends RelationManager
{
    protected static string $relationship = 'meritRankings';

    protected static ?string $title = 'Tabulation Sheet';

    public function table(Table $table): Table
    {
        $exam = $this->getOwnerRecord();
        $hasGroup = $exam->class?->has_group ?? false;
        $hasSections = $exam->class?->sections()->exists() ?? false;

        return $table
            ->columns([
                TextColumn::make('student.roll_no')
                    ->label('Roll')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('student.user.name')
                    ->label('Student Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('student.group.name')
                    ->label('Group')
                    ->alignCenter()
                    ->placeholder('—')
                    ->visible($hasGroup),

                TextColumn::make('section.name')
                    ->label('Section')
                    ->alignCenter()
                    ->placeholder('—')
                    ->visible($hasSections),

                TextColumn::make('total_marks')
                    ->label('Total Marks')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('gpa')
                    ->label('GPA')
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2))
                    ->color(fn ($state): string => GradeScale::fromGpa((float) $state)?->color ?? 'gray'),

                TextColumn::make('grade')
                    ->label('Grade')
                    ->alignCenter()
                    ->badge()
                    ->state(fn (StudentMeritRanking $record): string => GradeScale::fromGpa((float) $record->gpa)?->letter_grade ?? '—')
                    ->color(fn (StudentMeritRanking $record): string => GradeScale::fromGpa((float) $record->gpa)?->color ?? 'gray'),

                TextColumn::make('class_rank')
                    ->label('Rank')
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color('primary'),
            ])
            ->headerActions([
                Action::make('downloadTabulationSheet')
                    ->label('Download Tabulation Sheet (PDF)')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('info')
                    ->visible(fn (): bool => $this->getOwnerRecord()->meritRankings()->exists())
                    ->schema([
                        Section::make('Page Setup')
                            ->schema([
                                ViewComponent::make('filament.shared.orientation-preview'),

                                Radio::make('orientation')
                                    ->label('Orientation')
                                    ->options([
                                        'P' => 'Portrait',
                                        'L' => 'Landscape',
                                    ])
                                    ->default(fn (): string => $this->getOwnerRecord()->class?->level === ClassLevel::Primary ? 'P' : 'L')
                                    ->inline()
                                    ->inlineLabel(false)
                                    ->live()
                                    ->required(),

                                Radio::make('pageSize')
                                    ->label('Page Size')
                                    ->options([
                                        'A4' => 'A4',
                                        'A3' => 'A3',
                                        'Legal' => 'Legal',
                                    ])
                                    ->default('A4')
                                    ->inline()
                                    ->inlineLabel(false)
                                    ->required(),
                            ]),
                    ])
                    ->modalWidth('md')
                    ->modalHeading('Download Tabulation Sheet (PDF)')
                    ->modalSubmitActionLabel('Download')
                    ->action(fn (array $data) => $this->redirect(route('exams.tabulation-sheet.download', [
                        'exam' => $this->getOwnerRecord(),
                        'pageSize' => $data['pageSize'] ?? 'A4',
                        'orientation' => $data['orientation'] ?? 'P',
                    ]))),
            ])
            ->defaultSort('student.roll_no')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['student.user', 'student.group', 'section']))
            ->emptyStateHeading('এখনো কোনো ranking calculate করা হয়নি')
            ->emptyStateDescription('আগে Exam-এর "Calculate Rankings" বাটন থেকে ranking calculate করুন।')
            ->paginated(false);
    }
}
