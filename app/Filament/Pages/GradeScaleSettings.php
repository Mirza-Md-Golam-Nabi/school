<?php

namespace App\Filament\Pages;

use App\Models\GradeScale;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class GradeScaleSettings extends Page
{
    protected string $view = 'filament.pages.grade-scale-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'School Settings';

    protected static ?string $navigationLabel = 'Grading Scale';

    protected static ?string $title = 'Exam Grading Scale';

    public function mount(): void
    {
        $scales = GradeScale::query()->orderByDesc('min_mark')->get();

        $this->form->fill([
            'scales' => $scales->map(fn (GradeScale $scale): array => [
                'letter_grade' => $scale->letter_grade,
                'min_mark' => $scale->min_mark,
                'max_mark' => $scale->max_mark,
                'grade_point' => $scale->grade_point,
                'color' => $scale->color,
            ])->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Repeater::make('scales')
                    ->label('')
                    ->schema([
                        TextInput::make('letter_grade')
                            ->label('Letter Grade')
                            ->required()
                            ->maxLength(10)
                            ->extraInputAttributes(['class' => '!text-xs sm:!text-sm']),
                        TextInput::make('min_mark')
                            ->label('Min Mark')
                            ->helperText('Inclusive lower bound.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required()
                            ->extraInputAttributes(['class' => '!text-xs sm:!text-sm']),
                        TextInput::make('max_mark')
                            ->label('Max Mark')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required()
                            ->extraInputAttributes(['class' => '!text-xs sm:!text-sm']),
                        TextInput::make('grade_point')
                            ->label('Grade Point')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->required()
                            ->extraInputAttributes(['class' => '!text-xs sm:!text-sm']),
                        Select::make('color')
                            ->label('Color')
                            ->options([
                                'success' => 'Success (Green)',
                                'info' => 'Info (Blue)',
                                'warning' => 'Warning (Yellow)',
                                'danger' => 'Danger (Red)',
                                'gray' => 'Gray',
                            ])
                            ->native(false)
                            ->required()
                            ->default('gray'),
                    ])
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 5,
                    ])
                    ->addActionLabel('Add Grade')
                    ->reorderableWithButtons()
                    ->defaultItems(0)
                    ->deleteAction(fn (Action $action) => $action->requiresConfirmation())
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Grading Scale')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $rows = $this->form->getState()['scales'] ?? [];

        if (empty($rows)) {
            Notification::make()
                ->title('Add at least one grade before saving.')
                ->danger()
                ->send();

            return;
        }

        foreach ($rows as $row) {
            if ((float) $row['min_mark'] > (float) $row['max_mark']) {
                Notification::make()
                    ->title("Min Mark can't be greater than Max Mark for grade \"{$row['letter_grade']}\".")
                    ->danger()
                    ->send();

                return;
            }
        }

        DB::transaction(function () use ($rows): void {
            GradeScale::query()->delete();

            foreach ($rows as $row) {
                GradeScale::create([
                    'letter_grade' => $row['letter_grade'],
                    'min_mark' => $row['min_mark'],
                    'max_mark' => $row['max_mark'],
                    'grade_point' => $row['grade_point'],
                    'color' => $row['color'] ?? 'gray',
                ]);
            }
        });

        GradeScale::flushCache();

        $this->mount();

        Notification::make()
            ->title('Grading scale saved.')
            ->success()
            ->send();
    }
}
