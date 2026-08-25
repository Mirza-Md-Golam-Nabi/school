<?php

namespace App\Filament\Resources\Exams\RelationManagers;

use App\Actions\BuildStudentMarksDetail;
use App\Models\GradeScale;
use App\Models\StudentMeritRanking;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class MeritRankingsRelationManager extends RelationManager
{
    protected static string $relationship = 'meritRankings';

    protected static ?string $title = 'Merit Rankings';

    public function table(Table $table): Table
    {
        $hasSections = $this->getOwnerRecord()->class?->sections()->exists() ?? false;

        return $table
            ->columns([
                TextColumn::make('class_rank')
                    ->label('Class Rank')
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ((int) $state) {
                        1 => '🥇 1st',
                        2 => '🥈 2nd',
                        3 => '🥉 3rd',
                        default => (string) $state,
                    })
                    ->color(fn ($state): string => match ((int) $state) {
                        1 => 'warning',
                        2 => 'gray',
                        3 => 'danger',
                        default => 'primary',
                    }),

                TextColumn::make('section_rank')
                    ->label('Section Rank')
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->visible($hasSections),

                TextColumn::make('student.roll_no')
                    ->label('Roll')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('student.user.name')
                    ->label('Student Name')
                    ->searchable()
                    ->sortable(),

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
            ])
            ->filters([
                SelectFilter::make('section_id')
                    ->label('Section')
                    ->relationship('section', 'name')
                    ->placeholder('All Sections')
                    ->visible($hasSections),
            ])
            ->recordActions([
                Action::make('viewMarks')
                    ->label('Marks Detail')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->iconButton()
                    ->modalHeading(fn (StudentMeritRanking $record): string => ($record->student?->user?->name ?? 'Student').' — Subject Marks')
                    ->modalContent(fn (StudentMeritRanking $record): View => $this->buildMarksDetailView($record))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->defaultSort('class_rank')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['student.user', 'section']))
            ->paginated(false);
    }

    private function buildMarksDetailView(StudentMeritRanking $record): View
    {
        ['rows' => $rows, 'summary' => $summary] = app(BuildStudentMarksDetail::class)->handle($record);

        return view('filament.shared.student-marks-detail', compact('rows', 'summary'));
    }
}
