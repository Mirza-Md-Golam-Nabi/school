<?php

namespace App\Filament\Pages;

use App\Enums\MergedAbsentRule;
use App\Enums\MergedComponentPassRule;
use App\Enums\MergedPassRule;
use App\Enums\ResultRounding;
use App\Enums\WeekDay;
use App\Models\ResultRule;
use App\Models\SchoolSetting;
use App\Models\SubjectMerge;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class SchoolSettings extends Page
{
    protected string $view = 'filament.pages.school-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'School Settings';

    protected static ?string $navigationLabel = 'School Settings';

    protected static ?string $title = 'School Settings';

    public function mount(): void
    {
        $weekendRaw = SchoolSetting::get('weekend_days', '["friday"]');

        $this->form->fill([
            'school_name' => SchoolSetting::get('school_name', ''),
            SchoolSetting::SCHOOL_CODE_SETTING => SchoolSetting::schoolCode(),
            'weekend_days' => json_decode($weekendRaw, true) ?? ['friday'],
            'school_logo' => SchoolSetting::get('school_logo') ?: null,
            'school_address' => SchoolSetting::get('school_address', ''),
            SchoolSetting::EIIN_SETTING => SchoolSetting::get(SchoolSetting::EIIN_SETTING, ''),
            'school_established_year' => SchoolSetting::get('school_established_year') ?: null,
            'school_seal' => SchoolSetting::get('school_seal') ?: null,
            'principal_signature' => SchoolSetting::get('principal_signature') ?: null,
            SchoolSetting::HEAD_TITLE_SETTING => SchoolSetting::get(SchoolSetting::HEAD_TITLE_SETTING) ?: SchoolSetting::DEFAULT_HEAD_TITLE,
            SchoolSetting::HEAD_TITLE_BN_SETTING => SchoolSetting::get(SchoolSetting::HEAD_TITLE_BN_SETTING) ?: SchoolSetting::DEFAULT_HEAD_TITLE_BN,
            'admit_card_footer_text' => SchoolSetting::get('admit_card_footer_text', ''),
            'marksheet_use_watermark' => (bool) SchoolSetting::get('marksheet_use_watermark', '0'),
            'marksheet_watermark_text' => SchoolSetting::get('marksheet_watermark_text', ''),
            'marksheet_use_logo' => (bool) SchoolSetting::get('marksheet_use_logo', '1'),
            'marksheet_footer_text' => SchoolSetting::get('marksheet_footer_text', ''),
            ResultRule::DEFAULT_FULL_MARKS_SETTING => SchoolSetting::get(ResultRule::DEFAULT_FULL_MARKS_SETTING, ResultRule::DEFAULT_FULL_MARKS),
            ...array_combine(
                [SubjectMerge::PASS_RULE_SETTING, SubjectMerge::COMPONENT_PASS_RULE_SETTING, SubjectMerge::ABSENT_RULE_SETTING, SubjectMerge::ROUNDING_SETTING],
                array_map(fn (BackedEnum $rule): string => $rule->value, array_values(SubjectMerge::defaultRules())),
            ),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General')
                    ->schema([
                        TextInput::make('school_name')
                            ->label('School Name')
                            ->required(),
                        TextInput::make(SchoolSetting::SCHOOL_CODE_SETTING)
                            ->label('School Code')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(999)
                            ->required()
                            ->helperText('৮ ডিজিটের Student ID-র প্রথম ৩ ডিজিট (যেমন: 101 → 10100025)। শুধু নতুন student-এর ID-তে প্রযোজ্য — আগে তৈরি ID বদলাবে না।'),
                    ]),

                Section::make('Working Days')
                    ->schema([
                        CheckboxList::make('weekend_days')
                            ->label('Weekend Days')
                            ->options(WeekDay::options())
                            ->columns([
                                'default' => 2,  // mobile
                                'sm' => 4,        // tablet
                                'lg' => 7,        // laptop+
                            ])
                            ->gridDirection('row')
                            ->helperText('Select which days are weekends (non-working days).'),
                    ]),

                Section::make('Branding')
                    ->description('Used across generated documents (admit cards, marksheets, transfer certificates, ID cards).')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('school_logo')
                            ->label('School Logo')
                            ->image()
                            ->disk('public')
                            ->directory('school-settings/documents')
                            ->visibility('public')
                            ->imageEditor(),
                        FileUpload::make('school_seal')
                            ->label('School Seal / Stamp')
                            ->image()
                            ->disk('public')
                            ->directory('school-settings/documents')
                            ->visibility('public')
                            ->imageEditor(),
                        TextInput::make(SchoolSetting::HEAD_TITLE_SETTING)
                            ->label('Head Signatory Title (English)')
                            ->placeholder(SchoolSetting::DEFAULT_HEAD_TITLE)
                            ->datalist(['Principal', 'Head Teacher', 'Headmaster', 'Headmistress', 'Superintendent'])
                            ->maxLength(60)
                            ->required()
                            ->helperText('মার্কশিট, টেবুলেশন শিট, এডমিট কার্ড, ID কার্ড ইত্যাদিতে প্রধানের স্বাক্ষরের নিচে এই পদবি ছাপা হবে'),
                        TextInput::make(SchoolSetting::HEAD_TITLE_BN_SETTING)
                            ->label('Head Signatory Title (বাংলা)')
                            ->placeholder(SchoolSetting::DEFAULT_HEAD_TITLE_BN)
                            ->maxLength(60)
                            ->helperText('বাংলা ভাষার ডকুমেন্টে ছাপা হবে — খালি রাখলে ইংরেজি পদবি ছাপা হবে'),
                        FileUpload::make('principal_signature')
                            ->label('Head Signatory\'s Signature')
                            ->image()
                            ->disk('public')
                            ->directory('school-settings/documents')
                            ->visibility('public')
                            ->imageEditor(),
                        Textarea::make('school_address')
                            ->label('School Address')
                            ->rows(2)
                            ->columnSpanFull(),
                        TextInput::make(SchoolSetting::EIIN_SETTING)
                            ->label('EIIN Number')
                            ->maxLength(20)
                            ->regex('/^\s*[0-9]+\s*$/')
                            ->validationMessages(['regex' => 'EIIN নাম্বারে শুধু সংখ্যা দিন।'])
                            ->helperText('ডকুমেন্টে স্কুলের নামের নিচে "EIIN: …" হিসেবে ছাপা হবে'),
                        TextInput::make('school_established_year')
                            ->label('Established Year')
                            ->numeric()
                            ->minValue(1800)
                            ->maxValue((int) now()->format('Y'))
                            ->placeholder(now()->year),
                    ]),

                Section::make('Result')
                    ->schema([
                        TextInput::make(ResultRule::DEFAULT_FULL_MARKS_SETTING)
                            ->label('Result Full Marks')
                            ->helperText('রেজাল্টের নিয়মে (Result Rules) পূর্ণমান না দিলে রেজাল্ট এই মানে তৈরি হবে')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                    ]),

                Section::make('Merged Subjects')
                    ->description('দুইটা সাবজেক্ট মিলে একটা বিষয় (যেমন: বাংলা ১ম + ২য় পত্র = বাংলা) — সব মার্জের ডিফল্ট নিয়ম। কোনো মার্জে চাইলে ক্লাস বা পরীক্ষা থেকে আলাদা নিয়ম দেওয়া যায়।')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Select::make(SubjectMerge::PASS_RULE_SETTING)
                            ->label('Pass Rule')
                            ->options(MergedPassRule::class)
                            ->required(),
                        Select::make(SubjectMerge::COMPONENT_PASS_RULE_SETTING)
                            ->label('MCQ / Written / Practical Pass')
                            ->helperText('শুধু যৌথ নম্বরে পাশের নিয়মে খাটে')
                            ->options(MergedComponentPassRule::class)
                            ->required(),
                        Select::make(SubjectMerge::ABSENT_RULE_SETTING)
                            ->label('Absent In One Paper')
                            ->options(MergedAbsentRule::class)
                            ->required(),
                        Select::make(SubjectMerge::ROUNDING_SETTING)
                            ->label('Rounding')
                            ->helperText('যৌথ নম্বর (১০০-তে) রাউন্ড হয়, গ্রেড আসে রাউন্ড করা নম্বর থেকে')
                            ->options(ResultRounding::class)
                            ->required(),
                    ]),

                Section::make('Admit Card')
                    ->schema([
                        TextInput::make('admit_card_footer_text')
                            ->label('Footer Text'),
                    ]),

                Section::make('Marksheet')
                    ->columns(2)
                    ->schema([
                        Toggle::make('marksheet_use_logo')
                            ->label('Show School Logo'),
                        Toggle::make('marksheet_use_watermark')
                            ->label('Show Watermark')
                            ->live(),
                        TextInput::make('marksheet_watermark_text')
                            ->label('Watermark Text')
                            ->visible(fn (Get $get) => $get('marksheet_use_watermark'))
                            ->columnSpanFull(),
                        TextInput::make('marksheet_footer_text')
                            ->label('Footer Text')
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SchoolSetting::set('school_name', $data['school_name']);
        SchoolSetting::set(SchoolSetting::SCHOOL_CODE_SETTING, (string) (int) $data[SchoolSetting::SCHOOL_CODE_SETTING]);
        SchoolSetting::set('weekend_days', json_encode($data['weekend_days'] ?? []));
        SchoolSetting::set('school_logo', $data['school_logo'] ?? '');
        SchoolSetting::set('school_address', $data['school_address'] ?? '');
        SchoolSetting::set(SchoolSetting::EIIN_SETTING, trim((string) ($data[SchoolSetting::EIIN_SETTING] ?? '')));
        SchoolSetting::set('school_established_year', $data['school_established_year'] ?? '');
        SchoolSetting::set('school_seal', $data['school_seal'] ?? '');
        SchoolSetting::set('principal_signature', $data['principal_signature'] ?? '');
        SchoolSetting::set(SchoolSetting::HEAD_TITLE_SETTING, $data[SchoolSetting::HEAD_TITLE_SETTING]);
        SchoolSetting::set(SchoolSetting::HEAD_TITLE_BN_SETTING, $data[SchoolSetting::HEAD_TITLE_BN_SETTING] ?? '');
        SchoolSetting::set('admit_card_footer_text', $data['admit_card_footer_text'] ?? '');
        SchoolSetting::set('marksheet_use_watermark', $data['marksheet_use_watermark'] ? '1' : '0');
        SchoolSetting::set('marksheet_watermark_text', $data['marksheet_watermark_text'] ?? '');
        SchoolSetting::set('marksheet_use_logo', $data['marksheet_use_logo'] ? '1' : '0');
        SchoolSetting::set('marksheet_footer_text', $data['marksheet_footer_text'] ?? '');
        SchoolSetting::set(ResultRule::DEFAULT_FULL_MARKS_SETTING, $data[ResultRule::DEFAULT_FULL_MARKS_SETTING]);

        foreach ([SubjectMerge::PASS_RULE_SETTING, SubjectMerge::COMPONENT_PASS_RULE_SETTING, SubjectMerge::ABSENT_RULE_SETTING, SubjectMerge::ROUNDING_SETTING] as $key) {
            SchoolSetting::set($key, $data[$key] instanceof BackedEnum ? $data[$key]->value : $data[$key]);
        }

        Notification::make()
            ->title('Settings saved.')
            ->success()
            ->send();
    }
}
