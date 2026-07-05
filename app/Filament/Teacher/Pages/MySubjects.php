<?php

namespace App\Filament\Teacher\Pages;

use App\Models\TeacherProfile;
use App\Models\TeacherSubject;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MySubjects extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.teacher.pages.my-subjects';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Academics';

    protected static ?string $navigationLabel = 'My Subjects';

    protected static ?int $navigationSort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => TeacherSubject::query()
                ->where('teacher_id', $this->resolveTeacherProfile()?->id ?? 0)
                ->with(['subject', 'class', 'section'])
            )
            ->columns([
                TextColumn::make('subject.name')
                    ->label('Subject')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('class.name')
                    ->label('Class')
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
            ->defaultSort('session_year', 'desc')
            ->emptyStateHeading('No subjects assigned')
            ->emptyStateDescription('Your subject assignments will appear here once added by the admin.');
    }

    private function resolveTeacherProfile(): ?TeacherProfile
    {
        return auth()->user()?->teacherProfile;
    }
}
