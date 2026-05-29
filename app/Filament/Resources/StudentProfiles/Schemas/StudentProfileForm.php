<?php

namespace App\Filament\Resources\StudentProfiles\Schemas;

use App\Enums\BloodGroup;
use App\Enums\Gender;
use App\Enums\Religion;
use App\Enums\StudentStatus;
use App\Models\Classes;
use App\Models\Group;
use App\Models\Section as SectionModel;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class StudentProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account Information')
                    ->icon('heroicon-o-user-circle')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('name')
                                ->label('Full Name')
                                ->required(),

                            TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->unique(
                                    table: 'users',
                                    column: 'email',
                                    ignorable: fn ($record) => $record?->user,
                                )
                                ->required(),

                            TextInput::make('password')
                                ->label('Password')
                                ->password()
                                ->revealable()
                                ->required(fn (string $operation): bool => $operation === 'create')
                                ->helperText(fn (string $operation): ?string => $operation === 'edit'
                                    ? 'খালি রাখলে password পরিবর্তন হবে না'
                                    : null
                                ),
                        ]),
                    ]),

                Section::make('Academic Information')
                    ->icon('heroicon-o-academic-cap')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(4)->schema([
                            TextInput::make('roll_no')
                                ->label('Roll No')
                                ->integer()
                                ->minValue(1)
                                ->required(),

                            TextInput::make('registration_no')
                                ->label('Registration No')
                                ->integer()
                                ->minValue(1),

                            Select::make('session_year')
                                ->label('Session Year')
                                ->options(sessionYear())
                                ->default(now()->year)
                                ->required(),

                            Select::make('current_class_id')
                                ->label('Class')
                                ->options(fn () => Classes::pluck('name', 'id'))
                                ->searchable()
                                ->live()
                                ->required()
                                ->afterStateUpdated(function (Set $set) {
                                    $set('current_section_id', null);
                                    $set('current_group_id', null);
                                }),

                            Select::make('current_section_id')
                                ->label('Section')
                                ->options(fn (Get $get) => SectionModel::where('class_id', $get('current_class_id'))
                                    ->pluck('name', 'id')
                                )
                                ->searchable()
                                ->live(),

                            Select::make('current_group_id')
                                ->label('Group')
                                ->options(function (Get $get) {
                                    $classId = $get('current_class_id');
                                    if (! $classId) {
                                        return [];
                                    }

                                    return Classes::find($classId)
                                        ?->groups()
                                        ->pluck('groups.name', 'groups.id')
                                        ->toArray() ?? [];
                                })
                                ->searchable()
                                ->placeholder('No group'),

                            DatePicker::make('admission_date')
                                ->label('Admission Date'),

                            Select::make('status')
                                ->label('Status')
                                ->options(StudentStatus::class)
                                ->default(StudentStatus::Active->value)
                                ->required(),
                        ]),
                    ]),

                Section::make('Personal Information')
                    ->icon('heroicon-o-identification')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('gender')
                                ->label('Gender')
                                ->options(Gender::class)
                                ->required(),

                            DatePicker::make('date_of_birth')
                                ->label('Date of Birth'),

                            Select::make('blood_group')
                                ->label('Blood Group')
                                ->options(BloodGroup::class),

                            Select::make('religion')
                                ->label('Religion')
                                ->options(Religion::class),

                            TextInput::make('nationality')
                                ->label('Nationality')
                                ->default('Bangladeshi')
                                ->required(),
                        ]),
                    ]),

                Section::make('Family Information')
                    ->icon('heroicon-o-users')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('father_name')
                                ->label('Father\'s Name'),

                            TextInput::make('father_occupation')
                                ->label('Father\'s Occupation'),

                            FileUpload::make('father_photo')
                                ->label('Father\'s Photo')
                                ->image()
                                ->disk('public')
                                ->directory('student-profiles/parents')
                                ->visibility('public')
                                ->imageEditor(),

                            TextInput::make('mother_name')
                                ->label('Mother\'s Name'),

                            TextInput::make('mother_occupation')
                                ->label('Mother\'s Occupation'),

                            FileUpload::make('mother_photo')
                                ->label('Mother\'s Photo')
                                ->image()
                                ->disk('public')
                                ->directory('student-profiles/parents')
                                ->visibility('public')
                                ->imageEditor(),

                            TextInput::make('guardian_name')
                                ->label('Guardian\'s Name'),

                            TextInput::make('guardian_relation')
                                ->label('Relation with Guardian'),

                            TextInput::make('guardian_occupation')
                                ->label('Guardian\'s Occupation'),

                            FileUpload::make('guardian_photo')
                                ->label('Guardian\'s Photo')
                                ->image()
                                ->disk('public')
                                ->directory('student-profiles/parents')
                                ->visibility('public')
                                ->imageEditor()
                                ->columnSpanFull(),
                        ]),
                    ]),

                Section::make('Address')
                    ->icon('heroicon-o-map-pin')
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('present_address')
                            ->label('Present Address')
                            ->rows(3)
                            ->required()
                            ->columnSpanFull(),

                        Toggle::make('same_address')
                            ->label('Permanent address is same as present address')
                            ->live()
                            ->columnSpanFull(),

                        Textarea::make('permanent_address')
                            ->label('Permanent Address')
                            ->rows(3)
                            ->required(fn (Get $get): bool => ! (bool) $get('same_address'))
                            ->hidden(fn (Get $get): bool => (bool) $get('same_address'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
