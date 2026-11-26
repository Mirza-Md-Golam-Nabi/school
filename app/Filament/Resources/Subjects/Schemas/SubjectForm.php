<?php

namespace App\Filament\Resources\Subjects\Schemas;

use App\Models\Subject;
use Filament\Forms\Components\Select;
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
                            ->label('Subject Name (English)')
                            ->placeholder('e.g. Mathematics')
                            ->required()
                            ->maxLength(100),

                        TextInput::make('name_bn')
                            ->label('Subject Name (Bengali)')
                            ->placeholder('যেমন: গণিত')
                            ->maxLength(100),

                        TextInput::make('code')
                            ->label('Subject Code')
                            ->placeholder('e.g. 101')
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? strtoupper($state) : null),

                        Select::make('parent_subject_id')
                            ->label('Paper Of (Main Subject)')
                            ->placeholder('মূল বিষয় / পত্র নেই')
                            ->options(fn (?Subject $record): array => Subject::query()
                                ->whereNull('parent_subject_id')
                                ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                                ->ordered()
                                ->get()
                                ->pluck('display_name', 'id')
                                ->all())
                            ->searchable()
                            ->disabled(fn (?Subject $record): bool => (bool) $record?->papers()->exists())
                            ->helperText(fn (?Subject $record): string => $record?->papers()->exists()
                                ? 'এই বিষয়ের নিজেরই পত্র আছে, তাই এটা অন্য বিষয়ের পত্র হতে পারবে না।'
                                : 'কলেজের পত্রের জন্য (যেমন: Biology 1st Paper → Biology)। ক্লাসের সিলেবাসে শুধু মূল বিষয় যোগ করলেই পত্রগুলো পাওয়া যাবে।'),
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
