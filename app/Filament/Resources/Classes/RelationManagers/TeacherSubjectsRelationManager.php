<?php

namespace App\Filament\Resources\Classes\RelationManagers;

use App\Models\ClassGroupSubject;
use App\Models\Section;
use App\Models\TeacherProfile;
use App\Models\TeacherSubject;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TeacherSubjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'teacherSubjects';

    protected static ?string $title = 'Teachers';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('teacher_id')
                    ->label('Teacher')
                    ->options(fn () => TeacherProfile::dropdownOptions())
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('subject_id')
                    ->label('Subject')
                    ->options(function (RelationManager $livewire) {
                        $classId = $livewire->getOwnerRecord()->id;

                        return ClassGroupSubject::dropdownOptionsByClass($classId);
                    })
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('section_id')
                    ->label('Section')
                    ->placeholder('No Section')
                    ->options(function (RelationManager $livewire) {
                        $class = $livewire->getOwnerRecord();

                        if (! $class->has_section) {
                            return [];
                        }

                        return Section::dropdownOptionsByClass($class->id);
                    })
                    ->hidden(fn (RelationManager $livewire) => ! $livewire->getOwnerRecord()->has_section)
                    ->nullable(),

                Select::make('session_year')
                    ->label('Session Year')
                    ->options(function () {
                        $currentYear = (int) now()->format('Y');
                        $years = [];
                        for ($year = 2026; $year <= $currentYear; $year++) {
                            $years[$year] = (string) $year;
                        }

                        return $years;
                    })
                    ->default(fn () => (int) now()->format('Y'))
                    ->required(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('teacher.user.name')
                    ->label('Teacher')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subject.name')
                    ->label('Subject')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('section.name')
                    ->label('Section')
                    ->alignCenter()
                    ->placeholder('—'),

                TextColumn::make('session_year')
                    ->label('Session')
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color('info'),
            ])
            ->filters([
                SelectFilter::make('session_year')
                    ->label('Session Year')
                    ->options(function () {
                        $currentYear = (int) now()->format('Y');
                        $years = [];
                        for ($year = 2026; $year <= $currentYear; $year++) {
                            $years[$year] = (string) $year;
                        }

                        return $years;
                    })
                    ->default((int) now()->format('Y')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Assign Teacher')
                    ->modalHeading('Assign Teacher to Subject')
                    ->mutateDataUsing(function (array $data, RelationManager $livewire): array {
                        $data['class_id'] = $livewire->getOwnerRecord()->id;

                        return $data;
                    })
                    ->using(function (array $data): TeacherSubject {
                        return TeacherSubject::firstOrCreate(
                            [
                                'teacher_id' => $data['teacher_id'],
                                'subject_id' => $data['subject_id'],
                                'class_id' => $data['class_id'],
                                'section_id' => $data['section_id'] ?? null,
                                'session_year' => $data['session_year'],
                            ]
                        );
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->mutateDataUsing(function (array $data, RelationManager $livewire): array {
                        $data['class_id'] = $livewire->getOwnerRecord()->id;

                        return $data;
                    }),
                DeleteAction::make()
                    ->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['teacher.user', 'subject', 'section']))
            ->defaultSort('session_year', 'desc');
    }
}
