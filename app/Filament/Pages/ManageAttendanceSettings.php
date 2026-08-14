<?php

namespace App\Filament\Pages;

use App\Enums\AttendanceMode;
use App\Enums\Permissions\AttendancePermission;
use App\Filament\Concerns\HasAttendancePagePermission;
use App\Models\AttendanceSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageAttendanceSettings extends Page
{
    use HasAttendancePagePermission;

    protected string $view = 'filament.pages.manage-attendance-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?int $navigationSort = 99;

    protected static function attendancePermission(): AttendancePermission
    {
        return AttendancePermission::MANAGE_ATTENDANCE_SETTINGS;
    }

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->getRecord()?->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Attendance Mode')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->schema([
                            Grid::make(2)->schema([
                                Select::make('attendance_mode')
                                    ->label('Mode')
                                    ->options(AttendanceMode::class)
                                    ->required(),

                                TextInput::make('late_threshold_minutes')
                                    ->label('Late Threshold (minutes)')
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(120)
                                    ->required(),
                            ]),
                        ]),

                    Section::make('School Hours')
                        ->icon('heroicon-o-clock')
                        ->schema([
                            Grid::make(2)->schema([
                                TimePicker::make('entry_time')
                                    ->label('Entry Time (School Start)')
                                    ->seconds(false)
                                    ->required(),

                                TimePicker::make('exit_time')
                                    ->label('Exit Time (School End)')
                                    ->seconds(false)
                                    ->required(),
                            ]),
                        ]),
                ])->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save Settings')
                                ->submit('save'),
                        ]),
                    ]),
            ])
            ->record($this->getRecord())
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $record = $this->getRecord();

        if (! $record) {
            $record = new AttendanceSetting;
        }

        $record->fill($data)->save();

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->send();
    }

    public function getRecord(): ?AttendanceSetting
    {
        return AttendanceSetting::first();
    }
}
