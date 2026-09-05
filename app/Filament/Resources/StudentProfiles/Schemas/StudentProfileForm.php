<?php

namespace App\Filament\Resources\StudentProfiles\Schemas;

use App\Enums\BloodGroup;
use App\Enums\Gender;
use App\Enums\Religion;
use App\Enums\StudentStatus;
use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Models\Section as SectionModel;
use App\Models\StudentProfile;
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
use Illuminate\Support\Collection;
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
                        Grid::make(3)->schema([
                            TextInput::make('email')
                                ->label('Email')
                                ->disabled()
                                ->dehydrated(false)
                                ->hidden(fn (string $operation, Get $get): bool => $operation === 'create' && blank($get('email')))
                                ->helperText(fn (string $operation, Get $get): string => ($operation === 'create' && filled($get('email')))
                                    ? 'এই Birth Certificate No. আগে থেকেই নিবন্ধিত — নতুন account তৈরি না করে এই বিদ্যমান email-এ তথ্য আপডেট হবে এবং password রিসেট হয়ে যাবে'
                                    : 'এই ইমেইল সিস্টেম কর্তৃক স্বয়ংক্রিয়ভাবে তৈরি — পরিবর্তনযোগ্য নয়'),

                            TextInput::make('name')
                                ->label('Full Name')
                                ->required(),
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
                                ->options(fn () => Classes::orderBy('order')->pluck('name', 'id'))
                                ->searchable()
                                ->live()
                                ->required()
                                ->afterStateUpdated(function (Set $set) {
                                    $set('current_section_id', null);
                                    $set('current_group_id', null);
                                    $set('main_optional_subject_id', null);
                                    $set('extra_optional_subject_id', null);
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
                                        ->orderBy('groups.name')
                                        ->pluck('groups.name', 'groups.id')
                                        ->toArray() ?? [];
                                })
                                ->searchable()
                                ->placeholder('No group')
                                ->live()
                                ->afterStateUpdated(function (Set $set) {
                                    $set('main_optional_subject_id', null);
                                    $set('extra_optional_subject_id', null);
                                }),

                            Select::make('main_optional_subject_id')
                                ->label('Main Optional Subject')
                                ->options(fn (Get $get) => self::mainOptionalSubjectOptions($get))
                                ->visible(fn (Get $get): bool => self::mainOptionalSubjectOptions($get)->isNotEmpty())
                                ->searchable()
                                ->live()
                                ->placeholder('Select main optional subject'),

                            Select::make('extra_optional_subject_id')
                                ->label('Extra Optional Subject')
                                ->options(fn (Get $get) => self::extraOptionalSubjectOptions($get))
                                ->visible(fn (Get $get): bool => self::extraOptionalSubjectOptions($get)->isNotEmpty())
                                ->searchable()
                                ->placeholder('Select extra optional subject')
                                ->rules(fn (Get $get): array => [Rule::notIn(array_filter([$get('main_optional_subject_id')]))])
                                ->validationMessages([
                                    'not_in' => 'Extra optional subject must be different from the main optional subject.',
                                ]),

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

                            TextInput::make('birth_certificate_no')
                                ->label('Birth Certificate No')
                                ->extraInputAttributes([
                                    'inputmode' => 'numeric',
                                    'pattern' => '[0-9]*',
                                ])
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (string $operation, Set $set, ?string $state): void {
                                    if ($operation !== 'create') {
                                        return;
                                    }

                                    $set('email', filled($state)
                                        ? StudentProfile::where('birth_certificate_no', $state)->first()?->user?->email
                                        : null);
                                })
                                // On edit, a genuine duplicate against a DIFFERENT profile is still an
                                // error. On create, a match is handled specially (see afterStateUpdated
                                // above and CreateStudentProfileAction) rather than rejected — so no
                                // uniqueness rule applies there.
                                ->rules(fn (string $operation, $record): array => $operation === 'edit'
                                    ? [Rule::unique('student_profiles', 'birth_certificate_no')->ignore($record?->id)]
                                    : []),

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

                            TextInput::make('guardian_phone')
                                ->label('Guardian\'s Phone')
                                ->tel()
                                ->maxLength(15)
                                ->extraInputAttributes([
                                    'inputmode' => 'numeric',
                                    'pattern' => '[0-9]*',
                                ]),

                            FileUpload::make('guardian_photo')
                                ->label('Guardian\'s Photo')
                                ->image()
                                ->disk('public')
                                ->directory('student-profiles/parents')
                                ->visibility('public')
                                ->imageEditor(),
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

    /**
     * main_optional শুধু নির্বাচিত group-এর নিজস্ব optional subject থেকে বাছা
     * যায় (যেমন Science সিলেক্ট করলে শুধু Science-এর optional subject-ই এখানে
     * আসবে) — "All Groups" optional subject এখানে দেখানো হয় না।
     *
     * @return Collection<int, string>
     */
    private static function mainOptionalSubjectOptions(Get $get): Collection
    {
        return ClassGroupSubject::optionalSubjectOptions($get('current_class_id'), $get('current_group_id'), includeAllGroups: false);
    }

    /**
     * extra_optional-এর জন্য নির্বাচিত group-এর optional subject-এর পাশাপাশি
     * "All Groups" (group_id = null) optional subject-ও দেখানো হয়।
     *
     * @return Collection<int, string>
     */
    private static function extraOptionalSubjectOptions(Get $get): Collection
    {
        return ClassGroupSubject::optionalSubjectOptions($get('current_class_id'), $get('current_group_id'), includeAllGroups: true);
    }
}
