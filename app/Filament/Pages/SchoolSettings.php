<?php

namespace App\Filament\Pages;

use App\Enums\WeekDay;
use App\Models\SchoolSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
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
            'weekend_days' => json_decode($weekendRaw, true) ?? ['friday'],
            'school_logo' => SchoolSetting::get('school_logo') ?: null,
            'school_address' => SchoolSetting::get('school_address', ''),
            'school_established_year' => SchoolSetting::get('school_established_year') ?: null,
            'school_seal' => SchoolSetting::get('school_seal') ?: null,
            'principal_signature' => SchoolSetting::get('principal_signature') ?: null,
            'admit_card_use_watermark' => (bool) SchoolSetting::get('admit_card_use_watermark', '0'),
            'admit_card_watermark_text' => SchoolSetting::get('admit_card_watermark_text', ''),
            'admit_card_use_logo' => (bool) SchoolSetting::get('admit_card_use_logo', '1'),
            'admit_card_footer_text' => SchoolSetting::get('admit_card_footer_text', ''),
            'marksheet_use_watermark' => (bool) SchoolSetting::get('marksheet_use_watermark', '0'),
            'marksheet_watermark_text' => SchoolSetting::get('marksheet_watermark_text', ''),
            'marksheet_use_logo' => (bool) SchoolSetting::get('marksheet_use_logo', '1'),
            'marksheet_footer_text' => SchoolSetting::get('marksheet_footer_text', ''),
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
                        FileUpload::make('principal_signature')
                            ->label('Principal\'s Signature')
                            ->image()
                            ->disk('public')
                            ->directory('school-settings/documents')
                            ->visibility('public')
                            ->imageEditor(),
                        Textarea::make('school_address')
                            ->label('School Address')
                            ->rows(2)
                            ->columnSpanFull(),
                        TextInput::make('school_established_year')
                            ->label('Established Year')
                            ->numeric()
                            ->minValue(1800)
                            ->maxValue((int) now()->format('Y'))
                            ->placeholder(now()->year),
                    ]),

                Section::make('Admit Card')
                    ->columns(2)
                    ->schema([
                        Toggle::make('admit_card_use_logo')
                            ->label('Show School Logo'),
                        Toggle::make('admit_card_use_watermark')
                            ->label('Show Watermark')
                            ->live(),
                        TextInput::make('admit_card_watermark_text')
                            ->label('Watermark Text')
                            ->visible(fn (Get $get) => $get('admit_card_use_watermark'))
                            ->columnSpanFull(),
                        TextInput::make('admit_card_footer_text')
                            ->label('Footer Text')
                            ->columnSpanFull(),
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
        SchoolSetting::set('weekend_days', json_encode($data['weekend_days'] ?? []));
        SchoolSetting::set('school_logo', $data['school_logo'] ?? '');
        SchoolSetting::set('school_address', $data['school_address'] ?? '');
        SchoolSetting::set('school_established_year', $data['school_established_year'] ?? '');
        SchoolSetting::set('school_seal', $data['school_seal'] ?? '');
        SchoolSetting::set('principal_signature', $data['principal_signature'] ?? '');
        SchoolSetting::set('admit_card_use_watermark', $data['admit_card_use_watermark'] ? '1' : '0');
        SchoolSetting::set('admit_card_watermark_text', $data['admit_card_watermark_text'] ?? '');
        SchoolSetting::set('admit_card_use_logo', $data['admit_card_use_logo'] ? '1' : '0');
        SchoolSetting::set('admit_card_footer_text', $data['admit_card_footer_text'] ?? '');
        SchoolSetting::set('marksheet_use_watermark', $data['marksheet_use_watermark'] ? '1' : '0');
        SchoolSetting::set('marksheet_watermark_text', $data['marksheet_watermark_text'] ?? '');
        SchoolSetting::set('marksheet_use_logo', $data['marksheet_use_logo'] ? '1' : '0');
        SchoolSetting::set('marksheet_footer_text', $data['marksheet_footer_text'] ?? '');

        Notification::make()
            ->title('Settings saved.')
            ->success()
            ->send();
    }
}
