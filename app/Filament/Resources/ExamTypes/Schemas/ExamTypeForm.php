<?php

namespace App\Filament\Resources\ExamTypes\Schemas;

use App\Actions\CreateExamTypeAction;
use App\Enums\CountMethod;
use App\Enums\ExamConfigType;
use App\Models\Classes;
use App\Models\ExamType;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;

class ExamTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Exam Type Name')
                    ->placeholder('Enter exam type')
                    ->helperText('e.g: Yearly, Semester, Tutorial, Model Test etc')
                    ->required(),

                Select::make('type')
                    ->label('Behavior / Category')
                    ->helperText('এই পরীক্ষাকে কীভাবে গণনা করবে')
                    ->options(ExamConfigType::options())
                    ->live()
                    ->required(),

                Select::make('count_method')
                    ->label('Count Method')
                    ->options(CountMethod::class)
                    ->default(CountMethod::All->value)
                    ->visible(fn (Get $get): bool => $get('type') === ExamConfigType::Supporting->value)
                    ->live()
                    ->required(fn (Get $get): bool => $get('type') === ExamConfigType::Supporting->value),

                TextInput::make('best_n_count')
                    ->label('Best N Count')
                    ->helperText('কতটি সেরা পরীক্ষা গণনা করবে?')
                    ->numeric()
                    ->minValue(1)
                    ->visible(fn (Get $get): bool => $get('count_method') === CountMethod::BestN)
                    ->required(fn (Get $get): bool => $get('count_method') === CountMethod::BestN),

                Select::make('class_id')
                    ->label('Class')
                    ->options(fn () => Classes::pluck('name', 'id'))
                    ->searchable()
                    ->multiple()
                    ->visible(fn (Get $get): bool => $get('type') === ExamConfigType::Supporting->value)
                    ->required(fn (Get $get): bool => $get('type') === ExamConfigType::Supporting->value),

                Select::make('target_exam_type_id')
                    ->label('Target Exam')
                    ->helperText('যে পরীক্ষায় মার্কস যোগ হবে')
                    ->options(fn () => ExamType::pluck('name', 'id'))
                    ->searchable()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Exam Type Name')
                            ->placeholder('Enter exam type')
                            ->required(),

                        Select::make('type')
                            ->label('Behavior / Category')
                            ->helperText('এই পরীক্ষাকে কীভাবে গণনা করবে')
                            ->options(ExamConfigType::options())
                            ->live()
                            ->required(),

                        Select::make('count_method')
                            ->label('Count Method')
                            ->options(CountMethod::class)
                            ->default(CountMethod::All->value)
                            ->visible(fn (Get $get): bool => $get('type') === ExamConfigType::Supporting->value)
                            ->live()
                            ->required(fn (Get $get): bool => $get('type') === ExamConfigType::Supporting->value),

                        TextInput::make('best_n_count')
                            ->label('Best N Count')
                            ->helperText('কতটি সেরা পরীক্ষা গণনা করবে?')
                            ->numeric()
                            ->minValue(1)
                            ->visible(fn (Get $get): bool => $get('count_method') === CountMethod::BestN)
                            ->required(fn (Get $get): bool => $get('count_method') === CountMethod::BestN),

                        Select::make('class_id')
                            ->label('Class')
                            ->options(fn () => Classes::pluck('name', 'id'))
                            ->searchable()
                            ->multiple()
                            ->visible(fn (Get $get): bool => $get('type') === ExamConfigType::Supporting->value)
                            ->required(fn (Get $get): bool => $get('type') === ExamConfigType::Supporting->value),

                        Select::make('target_exam_type_id')
                            ->label('Target Exam')
                            ->helperText('যে পরীক্ষায় মার্কস যোগ হবে')
                            ->options(fn () => ExamType::pluck('name', 'id'))
                            ->searchable()
                            ->visible(fn (Get $get): bool => $get('type') === ExamConfigType::Supporting->value)
                            ->required(fn (Get $get): bool => $get('type') === ExamConfigType::Supporting->value),

                        TextInput::make('contribution_percent')
                            ->label('Contribution')
                            ->helperText('কতো মার্কস যোগ হবে?')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->suffix('+')
                            ->visible(fn (Get $get): bool => $get('type') === ExamConfigType::Supporting->value)
                            ->required(fn (Get $get): bool => $get('type') === ExamConfigType::Supporting->value),

                        Select::make('session_year')
                            ->options(sessionYear())
                            ->default(now()->year)
                            ->visible(fn (Get $get): bool => $get('type') === ExamConfigType::Supporting->value)
                            ->required(fn (Get $get): bool => $get('type') === ExamConfigType::Supporting->value),

                    ])
                    ->createOptionAction(fn (Action $action) => $action->modalWidth(Width::Medium))
                    ->createOptionUsing(fn (array $data) => app(CreateExamTypeAction::class)->handle($data)->id)
                    ->visible(fn (Get $get): bool => $get('type') === ExamConfigType::Supporting->value)
                    ->required(fn (Get $get): bool => $get('type') === ExamConfigType::Supporting->value),

                TextInput::make('contribution_percent')
                    ->label('Contribution')
                    ->helperText('কতো মার্কস যোগ হবে?')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->suffix('+')
                    ->visible(fn (Get $get): bool => $get('type') === ExamConfigType::Supporting->value)
                    ->required(fn (Get $get): bool => $get('type') === ExamConfigType::Supporting->value),

                Select::make('session_year')
                    ->options(sessionYear())
                    ->default(now()->year)
                    ->visible(fn (Get $get): bool => $get('type') === ExamConfigType::Supporting->value)
                    ->required(fn (Get $get): bool => $get('type') === ExamConfigType::Supporting->value),
            ]);
    }
}
