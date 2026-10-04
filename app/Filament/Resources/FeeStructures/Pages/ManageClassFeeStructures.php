<?php

namespace App\Filament\Resources\FeeStructures\Pages;

use App\Actions\CopyFeeStructuresToSessionYearAction;
use App\Actions\GenerateMonthlyFeeInvoicesAction;
use App\Filament\Resources\FeeStructures\FeeStructureResource;
use App\Models\Classes;
use App\Models\FeeStructure;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

class ManageClassFeeStructures extends ListRecords
{
    protected static string $resource = FeeStructureResource::class;

    #[Url(as: 'class')]
    public int $classId = 0;

    public function getTitle(): string|Htmlable
    {
        if ($this->classId) {
            return (Classes::query()->find($this->classId)?->name ?? 'Class').' — Fee Structures';
        }

        return 'Fee Structures';
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->where('class_id', $this->classId));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('All Classes')
                ->url(FeeStructureResource::getUrl('index'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray'),
            Action::make('generateMonthlyInvoices')
                ->label('Generate Monthly Invoices')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->visible(fn (): bool => (bool) $this->classId)
                ->schema([
                    Select::make('month')
                        ->label('Month')
                        ->options(fn () => collect(range(1, 12))
                            ->mapWithKeys(fn (int $m) => [$m => Carbon::create()->month($m)->format('F')]))
                        ->native(false)
                        ->default(now()->month)
                        ->required(),
                    Select::make('year')
                        ->label('Year')
                        ->options(fn () => collect(range(now()->year - 1, now()->year + 1))
                            ->mapWithKeys(fn (int $y) => [$y => $y]))
                        ->native(false)
                        ->default(now()->year)
                        ->required(),
                ])
                ->modalHeading(fn (): string => 'Generate Monthly Invoices — '.(Classes::query()->find($this->classId)?->name ?? 'Class'))
                ->modalSubmitActionLabel('Generate')
                ->action(function (array $data): void {
                    $result = app(GenerateMonthlyFeeInvoicesAction::class)
                        ->handle((int) $data['month'], (int) $data['year'], $this->classId);

                    Notification::make()
                        ->title('Monthly invoices generated')
                        ->body("Generated: {$result['generated']} | Skipped (already exists): {$result['skipped']}")
                        ->success()
                        ->send();
                }),
            Action::make('copyToNewSession')
                ->label('Copy to New Session')
                ->icon('heroicon-o-document-duplicate')
                ->color('warning')
                ->visible(fn (): bool => (bool) $this->classId)
                ->fillForm(function (): array {
                    $latestYear = $this->classSessionYears()->first();

                    return [
                        'from_year' => $latestYear,
                        'to_year' => $latestYear ? $latestYear + 1 : null,
                    ];
                })
                ->schema([
                    Select::make('from_year')
                        ->label('Copy From Session Year')
                        ->options(fn (): array => $this->classSessionYears()->mapWithKeys(fn (int $year): array => [$year => $year])->all())
                        ->native(false)
                        ->required(),
                    TextInput::make('to_year')
                        ->label('New Session Year')
                        ->numeric()
                        ->minValue(2000)
                        ->maxValue(2100)
                        ->different('from_year')
                        ->required(),
                ])
                ->modalHeading(fn (): string => 'Copy to New Session — '.(Classes::query()->find($this->classId)?->name ?? 'Class'))
                ->modalDescription('এই ক্লাসের নির্বাচিত সেশনের সব active fee structure নতুন সেশনে কপি হবে। পুরনো সেশনের structure যেমন আছে তেমনই থাকবে।')
                ->modalSubmitActionLabel('Copy')
                ->action(function (array $data): void {
                    $result = app(CopyFeeStructuresToSessionYearAction::class)
                        ->handle((int) $data['from_year'], (int) $data['to_year'], $this->classId);

                    Notification::make()
                        ->title('Fee structures copied')
                        ->body("Copied: {$result['copied']} | Skipped (already exists in {$data['to_year']}): {$result['skipped']}")
                        ->success()
                        ->send();
                }),
            CreateAction::make()
                ->url(fn (): string => FeeStructureResource::getUrl(
                    'create',
                    $this->classId ? ['class_id' => $this->classId] : []
                )),
        ];
    }

    /**
     * @return Collection<int, int>
     */
    private function classSessionYears(): Collection
    {
        return FeeStructure::where('class_id', $this->classId)
            ->distinct()
            ->orderByDesc('session_year')
            ->pluck('session_year');
    }
}
