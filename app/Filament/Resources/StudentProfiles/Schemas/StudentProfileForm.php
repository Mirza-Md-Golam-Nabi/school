<?php

namespace App\Filament\Resources\StudentProfiles\Schemas;

use App\Enums\BloodGroup;
use App\Enums\Gender;
use App\Enums\Religion;
use App\Enums\StudentStatus;
use App\Models\Classes;
use App\Models\Section as SectionModel;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

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
                        Hidden::make('user_found')->default(false)->saved(false),
                        Hidden::make('has_profile')->default(false)->saved(false),
                        Hidden::make('email_is_manual')->default(false)->saved(false),

                        Grid::make(3)->schema([
                            TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                                    $set('email_is_manual', true);

                                    if (blank($state)) {
                                        $set('user_found', false);
                                        $set('has_profile', false);

                                        return;
                                    }

                                    $user = User::where('email', $state)->first();

                                    if ($user) {
                                        $set('user_found', true);
                                        $set('has_profile', $user->studentProfile !== null);

                                        if (blank($get('name'))) {
                                            $set('name', $user->name);
                                        }
                                    } else {
                                        $set('user_found', false);
                                        $set('has_profile', false);
                                    }
                                })
                                ->rules(fn (string $operation, $record): array => $operation === 'edit'
                                        ? [Rule::unique('users', 'email')->ignore($record?->user?->id)]
                                        : []
                                )
                                ->helperText(fn (string $operation, Get $get): ?string => match (true) {
                                    $operation !== 'create' => null,
                                    (bool) $get('has_profile') => '⚠️ এই ইমেইলে ইতিমধ্যে student profile আছে — সেটি আপডেট হবে',
                                    (bool) $get('user_found') => 'ℹ️ এই ইমেইলে account আছে — profile তৈরি হবে',
                                    default => null,
                                }),

                            TextInput::make('name')
                                ->label('Full Name')
                                ->required(),

                            TextInput::make('password')
                                ->label('Password')
                                ->password()
                                ->revealable()
                                ->default(fn (string $operation): ?string => $operation === 'create' ? 'password' : null)
                                ->required(fn (string $operation, Get $get): bool => $operation === 'create' && ! (bool) $get('user_found')
                                )
                                ->helperText(fn (string $operation, Get $get): ?string => match (true) {
                                    $operation === 'edit' => 'খালি রাখলে password পরিবর্তন হবে না',
                                    (bool) $get('user_found') => 'ব্যবহারকারী পাওয়া গেছে — password পরিবর্তন হবে না',
                                    default => null,
                                }),
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
                                ->live(onBlur: true)
                                ->required()
                                ->afterStateUpdated(function (Set $set, Get $get, string $operation) {
                                    self::fillGeneratedEmail($set, $get, $operation);
                                }),

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
                                ->afterStateUpdated(function (Set $set, Get $get, string $operation) {
                                    $set('current_section_id', null);
                                    $set('current_group_id', null);
                                    self::fillGeneratedEmail($set, $get, $operation);
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
                                ->default(Gender::Male)
                                ->required(),

                            DatePicker::make('date_of_birth')
                                ->label('Date of Birth'),

                            Select::make('blood_group')
                                ->label('Blood Group')
                                ->options(BloodGroup::class),

                            Select::make('religion')
                                ->label('Religion')
                                ->options(Religion::class)
                                ->default(Religion::Muslim),

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
                            ->columnSpanFull(),

                        Toggle::make('same_address')
                            ->label('Permanent address is same as present address')
                            ->live()
                            ->columnSpanFull(),

                        Textarea::make('permanent_address')
                            ->label('Permanent Address')
                            ->rows(3)
                            ->hidden(fn (Get $get): bool => (bool) $get('same_address'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function fillGeneratedEmail(Set $set, Get $get, string $operation): void
    {
        if ($operation !== 'create') {
            return;
        }

        if ((bool) $get('email_is_manual')) {
            return;
        }

        $rollNo = $get('roll_no');
        $classId = $get('current_class_id');

        if (blank($rollNo) || blank($classId)) {
            return;
        }

        $classNumber = Classes::find($classId)?->order;

        if ($classNumber === null) {
            return;
        }

        $email = sprintf('class_%02d_%02d@example.com', $classNumber, $rollNo);

        if (User::where('email', $email)->exists()) {
            $nextId = (User::max('id') ?? 0) + 1;
            $email = sprintf('class_%02d_%02d_%d@example.com', $classNumber, $rollNo, $nextId);
        }

        $set('email', $email);
    }
}
