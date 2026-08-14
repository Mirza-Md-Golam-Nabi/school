<?php

namespace App\Filament\Resources\Classes\Schemas;

use App\Enums\ClassLevel;
use App\Models\TeacherProfile;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ClassesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->placeholder('Enter Class Name')
                    ->helperText('Ex: Class 1, Class 2'),
                Select::make('level')
                    ->options(ClassLevel::options())
                    ->required(),
                TextInput::make('order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Select::make('class_teacher_id')
                    ->label('Class Teacher')
                    ->options(TeacherProfile::dropdownOptions())
                    ->searchable()
                    ->helperText('This teacher is authorized to mark attendance for this class. If another teacher marks it instead, admins and this teacher are notified.'),
                Radio::make('has_section')
                    ->label('Has Section?')
                    ->required()
                    ->default(false)
                    ->boolean()
                    ->inline()
                    ->inlineLabel(false),
                Radio::make('has_group')
                    ->label('Has Group?')
                    ->required()
                    ->default(false)
                    ->boolean()
                    ->inline()
                    ->inlineLabel(false),
                Radio::make('is_active')
                    ->label('Is Active?')
                    ->default(true)
                    ->boolean()
                    ->inline()
                    ->inlineLabel(false),
            ]);
    }
}
