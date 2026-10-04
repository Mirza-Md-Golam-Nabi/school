<?php

namespace App\Filament\Pages;

use App\Models\Classes;
use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 * @property-read Schema $employeeForm
 */
class StudentAttendanceReport extends Page
{
    protected string $view = 'filament.pages.student-attendance-report';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $employeeData = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Document Management';

    protected static ?string $navigationLabel = 'Attendance Report';

    protected static ?string $title = 'Attendance Report';

    public function mount(): void
    {
        $this->form->fill([
            'session_year' => now()->year,
            'month' => now()->month,
        ]);

        $this->employeeForm->fill([
            'year' => now()->year,
            'month' => now()->month,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('class_ids')
                    ->label('Classes')
                    ->options(fn () => Classes::query()->active()->orderBy('order')->pluck('name', 'id'))
                    ->multiple()
                    ->searchable()
                    ->native(false)
                    ->required(),
                Select::make('month')
                    ->label('Month')
                    ->options(self::monthOptions())
                    ->native(false)
                    ->required(),
                TextInput::make('session_year')
                    ->label('Session Year')
                    ->numeric()
                    ->minValue(2000)
                    ->maxValue((int) now()->format('Y') + 1)
                    ->required(),
            ])
            ->columns([
                'default' => 1,
                'sm' => 3,
            ])
            ->statePath('data');
    }

    public function employeeForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Teacher & Staff Attendance Report'))
                    ->description(__('Select one or more teachers or staff and a month. One PDF is downloaded, with each person on their own page.'))
                    ->schema([
                        Select::make('people')
                            ->label(__('Teachers & Staff'))
                            ->options(fn (): array => self::employeeOptions())
                            ->multiple()
                            ->searchable()
                            ->native(false)
                            ->required(),
                        Select::make('month')
                            ->label('Month')
                            ->options(self::monthOptions())
                            ->native(false)
                            ->required(),
                        TextInput::make('year')
                            ->label('Year')
                            ->numeric()
                            ->minValue(2000)
                            ->maxValue((int) now()->format('Y') + 1)
                            ->required(),
                        Actions::make([
                            Action::make('downloadEmployeeReport')
                                ->label(__('Download Teacher & Staff Report (PDF)'))
                                ->icon(Heroicon::OutlinedArrowDownTray)
                                ->action(fn () => $this->downloadEmployeeReport()),
                        ])->columnSpanFull(),
                    ])
                    ->columns([
                        'default' => 1,
                        'sm' => 3,
                    ]),
            ])
            ->statePath('employeeData');
    }

    /**
     * Sends the browser to the PDF for the selected teachers and staff.
     */
    public function downloadEmployeeReport(): void
    {
        $data = $this->employeeForm->getState();

        $this->dispatch('download-attendance-reports', urls: [
            route('attendance-report.employees.download', [
                'year' => $data['year'],
                'month' => $data['month'],
                'people' => array_values($data['people']),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label(__('Download Student Reports (PDF)'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(function () {
                    $data = $this->form->getState();

                    $urls = collect($data['class_ids'])
                        ->map(fn ($classId) => route('attendance-report.class.download', [
                            'class' => $classId,
                            'year' => $data['session_year'],
                            'month' => $data['month'],
                        ]))
                        ->all();

                    $this->dispatch('download-attendance-reports', urls: $urls);
                }),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function monthOptions(): array
    {
        return [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];
    }

    /**
     * Active teachers and staff grouped by kind, keyed the way the download URL
     * expects them ("teacher-12", "staff-4").
     *
     * @return array<string, array<string, string>>
     */
    private static function employeeOptions(): array
    {
        $optionsFor = fn (string $kind, string $model): array => $model::query()
            ->active()
            ->with('user')
            ->get()
            ->sortBy('user.name')
            ->mapWithKeys(fn (TeacherProfile|StaffProfile $person): array => [
                "{$kind}-{$person->id}" => $person->user?->name ?? "#{$person->id}",
            ])
            ->all();

        return array_filter([
            __('Teachers') => $optionsFor('teacher', TeacherProfile::class),
            __('Staff') => $optionsFor('staff', StaffProfile::class),
        ]);
    }
}
