<?php

namespace App\Filament\Resources\AdmitCards\Schemas;

use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Grid;

/**
 * The two language choices of both Generate Admit Cards windows: the exam
 * routine's language and the student details' language, each English or
 * বাংলা on its own.
 */
class AdmitCardLanguageRadios
{
    private const LANGUAGES = [
        'en' => 'English',
        'bn' => 'বাংলা',
    ];

    public static function make(): Grid
    {
        return Grid::make(['default' => 1, 'sm' => 2])
            ->schema([
                Radio::make('routine_language')
                    ->label('Exam Routine Language')
                    ->options(self::LANGUAGES)
                    ->default('en')
                    ->inline()
                    ->helperText('বাংলা হলে রুটিনের তারিখ ও বিষয়ের নাম বাংলায় হবে (শুধু রুটিনসহ ডিজাইনে)।')
                    ->required(),

                Radio::make('student_info_language')
                    ->label('Student Info Language')
                    ->options(self::LANGUAGES)
                    ->default('en')
                    ->inline()
                    ->helperText('বাংলা হলে শিক্ষার্থীর তথ্যের লেবেল, শ্রেণি, বিভাগ ও সংখ্যা বাংলায় হবে।')
                    ->required(),
            ])
            ->columnSpanFull();
    }
}
