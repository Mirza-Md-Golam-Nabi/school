<?php

namespace App\Filament\Resources\ExamTypes\Schemas;

use App\Enums\CountMethod;
use App\Enums\ExamConfigType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

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
                    ->helperText('কোন পরীক্ষা কোন রেজাল্টে কত % যোগ হবে, সেটা "Result Rules" পেজে ঠিক করুন')
                    ->options(ExamConfigType::options())
                    ->default(ExamConfigType::Main->value)
                    ->required(),

                Select::make('count_method')
                    ->label('Count Method')
                    ->helperText('রেজাল্টের নিয়মে এই ধরনের একাধিক পরীক্ষা থাকলে (যেমন CT-1, CT-2, CT-3) কোনগুলো গোনা হবে')
                    ->options(CountMethod::class)
                    ->default(CountMethod::All->value)
                    ->live()
                    ->required(),

                TextInput::make('best_n_count')
                    ->label('Best N Count')
                    ->helperText('কতটি সেরা পরীক্ষা গণনা করবে?')
                    ->numeric()
                    ->minValue(1)
                    ->visible(fn (Get $get): bool => $get('count_method') === CountMethod::BestN)
                    ->required(fn (Get $get): bool => $get('count_method') === CountMethod::BestN),
            ]);
    }
}
