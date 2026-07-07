<?php

namespace App\Filament\Resources\Exams\RelationManagers;

use App\Enums\Grade;
use App\Models\ExamSubjectConfig;
use App\Models\StudentMeritRanking;
use App\Models\StudentResult;
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
                    ->placeholder('—'),

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
                    ->placeholder('—'),

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
                    ->color(fn ($state): string => Grade::fromGpa((float) $state)->getColor()),

                TextColumn::make('grade')
                    ->label('Grade')
                    ->alignCenter()
                    ->badge()
                    ->state(fn (StudentMeritRanking $record): string => Grade::fromGpa((float) $record->gpa)->getLabel())
                    ->color(fn (StudentMeritRanking $record): string => Grade::fromGpa((float) $record->gpa)->getColor()),
            ])
            ->filters([
                SelectFilter::make('section_id')
                    ->label('Section')
                    ->relationship('section', 'name')
                    ->placeholder('All Sections'),
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
        $myResults = StudentResult::where('exam_id', $record->exam_id)
            ->where('student_id', $record->student_id)
            ->with('subject')
            ->get()
            ->keyBy('subject_id');

        $subjectConfigs = ExamSubjectConfig::where('exam_id', $record->exam_id)
            ->get()
            ->keyBy('subject_id');

        $allResults = StudentResult::where('exam_id', $record->exam_id)
            ->with('student.user')
            ->get()
            ->groupBy('subject_id');

        $subjectBestMap = [];
        foreach ($allResults as $subjectId => $results) {
            $bestMarks = $results->max('total_marks');
            $subjectBestMap[$subjectId] = [
                'best_marks' => $bestMarks,
                'best_students' => $results
                    ->filter(fn ($r) => (float) $r->total_marks === (float) $bestMarks && ! $r->is_absent)
                    ->map(fn ($r) => $r->student?->user?->name)
                    ->filter()
                    ->implode(', '),
            ];
        }

        $rows = $myResults->map(function (StudentResult $result) use ($subjectConfigs, $subjectBestMap): array {
            $subjectId = $result->subject_id;
            $config = $subjectConfigs->get($subjectId);
            $fullMarks = $config?->total_marks ?: 100;
            $percentage = (! $result->is_absent && $fullMarks > 0)
                ? ($result->total_marks / $fullMarks) * 100
                : 0.0;
            $grade = (! $result->is_absent && $result->total_marks > 0)
                ? Grade::fromMarks($percentage)
                : null;
            $best = $subjectBestMap[$subjectId] ?? null;

            return [
                'subject_name' => $result->subject?->name ?? '—',
                'is_absent' => $result->is_absent,
                'total_marks' => $result->total_marks,
                'grade_label' => $grade?->getLabel(),
                'grade_color' => $grade?->getColor(),
                'is_top_scorer' => $best !== null
                    && ! $result->is_absent
                    && (float) $result->total_marks === (float) $best['best_marks'],
                'best_marks' => $best['best_marks'] ?? null,
                'best_students' => $best['best_students'] ?? null,
            ];
        })->values();

        $overallGrade = Grade::fromGpa((float) $record->gpa);

        $summary = [
            'total_marks' => $record->total_marks,
            'class_rank' => $record->class_rank,
            'section_rank' => $record->section_rank,
            'gpa' => number_format((float) $record->gpa, 2),
            'overall_grade_label' => $overallGrade->getLabel(),
            'overall_grade_color' => $overallGrade->getColor(),
        ];

        return view('filament.shared.student-marks-detail', compact('rows', 'summary'));
    }
}
