<?php

namespace App\Filament\Resources\Exams\Schemas;

use App\Models\Classes;
use App\Models\ExamType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ExamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('exam_type_id')
                    ->label('Exam Type')
                    ->options(fn () => ExamType::pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                Select::make('class_id')
                    ->label('Class')
                    ->options(fn () => Classes::pluck('name', 'id'))
                    ->multiple(fn (string $operation): bool => $operation === 'create')
                    ->searchable()
                    ->required(),

                Select::make('session_year')
                    ->label('Session Year')
                    ->options(sessionYear())
                    ->default(now()->year)
                    ->required(),

                Toggle::make('is_published')
                    ->label('Published')
                    ->helperText('Published হলে students দেখতে পাবে')
                    ->default(false),

                DatePicker::make('start_date')
                    ->label('Start Date')
                    ->required()
                    ->before('end_date'),

                DatePicker::make('end_date')
                    ->label('End Date')
                    ->required()
                    ->after('start_date'),
            ]);
    }
}
