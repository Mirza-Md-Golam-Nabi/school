<?php

namespace App\Filament\Resources\Subjects\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Subject Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Subject Name')
                            ->placeholder('e.g. Mathematics')
                            ->required()
                            ->maxLength(100),

                        TextInput::make('code')
                            ->label('Subject Code')
                            ->placeholder('e.g. MAT')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->dehydrateStateUsing(fn ($state) => strtoupper($state)),
                    ])
                    ->columns([
                        'sm' => 1,
                        'md' => 2,
                        'default' => 1,
                    ]),

                Section::make('Exam Components')
                    ->description('এই সাবজেক্টে কোন কোন পরীক্ষার অংশ আছে তা নির্বাচন করুন।')
                    ->schema([
                        Toggle::make('has_written')
                            ->label('Written Exam')
                            ->default(true)
                            ->inline(false),

                        Toggle::make('has_mcq')
                            ->label('MCQ Exam')
                            ->default(false)
                            ->inline(false),

                        Toggle::make('has_practical')
                            ->label('Practical Exam')
                            ->default(false)
                            ->inline(false),
                    ])
                    ->columns([
                        'sm' => 3,
                        'default' => 2,
                    ]),

                Section::make('Status')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(1),
            ])
            ->columns([
                'lg' => 1,
                'xl' => 2,
            ]);
    }
}
