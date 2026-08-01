<?php

namespace App\Filament\Resources\TeacherProfiles\Schemas;

use App\Enums\BloodGroup;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\Religion;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
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

class TeacherProfileForm
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

                        Grid::make(3)->schema([
                            TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                                    if (blank($state)) {
                                        $set('user_found', false);
                                        $set('has_profile', false);

                                        return;
                                    }

                                    $user = User::where('email', $state)->first();

                                    if ($user) {
                                        $set('user_found', true);
                                        $set('has_profile', $user->teacherProfile !== null);

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
                                    (bool) $get('has_profile') => '⚠️ এই ইমেইলে ইতিমধ্যে teacher profile আছে — সেটি আপডেট হবে',
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
                                ->required(fn (string $operation, Get $get): bool => $operation === 'create' && ! (bool) $get('user_found'))
                                ->helperText(fn (string $operation, Get $get): ?string => match (true) {
                                    $operation === 'edit' => 'খালি রাখলে password পরিবর্তন হবে না',
                                    (bool) $get('user_found') => 'ব্যবহারকারী পাওয়া গেছে — password পরিবর্তন হবে না',
                                    default => null,
                                }),
                        ]),
                    ]),

                Section::make('Professional Information')
                    ->icon('heroicon-o-briefcase')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('designation')
                                ->label('Designation'),

                            TextInput::make('department')
                                ->label('Department'),

                            TextInput::make('qualification')
                                ->label('Qualification'),

                            DatePicker::make('joining_date')
                                ->label('Joining Date'),

                            Select::make('status')
                                ->label('Status')
                                ->options(EmploymentStatus::class)
                                ->default(EmploymentStatus::Active->value)
                                ->required(),

                            Select::make('default_school_account_id')
                                ->label('Default Salary Account')
                                ->relationship('defaultSchoolAccount', 'name')
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->helperText('Salary payment form-এ ডিফল্ট account হিসেবে বসবে'),
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
                                ->options(Religion::class),

                            TextInput::make('nationality')
                                ->label('Nationality')
                                ->default('Bangladeshi')
                                ->required(),
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
}
