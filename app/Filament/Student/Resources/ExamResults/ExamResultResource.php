<?php

namespace App\Filament\Student\Resources\ExamResults;

use App\Filament\Student\Resources\ExamResults\Pages\ListExamResults;
use App\Filament\Student\Resources\ExamResults\Pages\ViewExamResult;
use App\Models\Exam;
use App\Models\StudentProfile;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ExamResultResource extends Resource
{
    protected static ?string $model = Exam::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'Results';

    protected static ?string $navigationLabel = 'Exam Results';

    protected static ?string $modelLabel = 'Exam Result';

    protected static ?string $pluralModelLabel = 'Exam Results';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('examType.name')
                    ->label('Exam')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('session_year')
                    ->label('Session')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('End Date')
                    ->date()
                    ->sortable(),

                IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->modifyQueryUsing(function (Builder $query): void {
                $student = static::getStudentProfile();

                if (! $student) {
                    $query->whereRaw('0=1');

                    return;
                }

                $query->where('class_id', $student->current_class_id)
                    ->where('is_published', true)
                    ->whereHas('meritRankings');
            })
            ->recordUrl(fn (Exam $record): string => static::getUrl('view', ['record' => $record]))
            ->defaultSort('start_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExamResults::route('/'),
            'view' => ViewExamResult::route('/{record}'),
        ];
    }

    public static function getStudentProfile(): ?StudentProfile
    {
        /** @var User $user */
        $user = auth()->user();

        return StudentProfile::where('user_id', $user->id)->first();
    }
}
