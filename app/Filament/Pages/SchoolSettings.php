<?php

namespace App\Filament\Pages;

use App\Enums\WeekDay;
use App\Models\SchoolSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
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

        Notification::make()
            ->title('Settings saved.')
            ->success()
            ->send();
    }
}
