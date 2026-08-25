<?php

namespace App\Filament\Pages;

use App\Models\Classes;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class StudentAttendanceReport extends Page
{
    protected string $view = 'filament.pages.student-attendance-report';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Document Management';

    protected static ?string $navigationLabel = 'Attendance Report';

    protected static ?string $title = 'Student Attendance Report';

    public function mount(): void
    {
        $this->form->fill([
            'session_year' => now()->year,
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
                    ->options([
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
                    ])
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Download Reports (PDF)')
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
}
