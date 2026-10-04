<?php

namespace App\Filament\Resources\FeeStructures\Pages;

use App\Actions\CopyFeeStructuresToSessionYearAction;
use App\Actions\GenerateMonthlyFeeInvoicesAction;
use App\Actions\GenerateOneTimeFeeInvoicesForAllClassesAction;
use App\Filament\Resources\FeeStructures\FeeStructureResource;
use App\Models\Classes;
use App\Models\FeeStructure;
use App\Models\FeeType;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;

class ListFeeStructures extends Page
{
    protected static string $resource = FeeStructureResource::class;

    protected string $view = 'filament.resources.fee-structures.pages.list-fee-structures';

    /**
     * প্রতিটা ক্লাসের কার্ডে শুধু তার সর্বশেষ সেশনের active fee structure দেখায় —
     * নতুন সেশনে কপি করার পর পুরনো ও নতুন বছরের ফি যাতে পাশাপাশি না আসে।
     *
     * @return array{classes: Collection<int, Classes>}
     */
    protected function getViewData(): array
    {
        $classes = Classes::with([
            'feeStructures' => fn ($query) => $query->where('is_active', true)->with('feeType'),
        ])
            ->active()
            ->orderBy('order')
            ->get()
            ->each(function (Classes $class): void {
                $latestSessionYear = $class->feeStructures->max('session_year');

                $class->setRelation(
                    'feeStructures',
                    $class->feeStructures
                        ->where('session_year', $latestSessionYear)
                        ->sortBy('feeType.name')
                        ->values()
                );
            });

        return ['classes' => $classes];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateInvoices')
                ->label('Generate Invoices')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->schema([
                    Select::make('invoice_type')
                        ->label('Invoice Type')
                        ->options([
                            'monthly' => 'Monthly Fee',
                            'one_time' => 'One-Time Fee',
                        ])
                        ->native(false)
                        ->live()
                        ->default('monthly')
                        ->required(),
                    Select::make('month')
                        ->label('Month')
                        ->options(fn () => collect(range(1, 12))
                            ->mapWithKeys(fn (int $m) => [$m => Carbon::create()->month($m)->format('F')]))
                        ->native(false)
                        ->default(now()->month)
                        ->visible(fn (Get $get): bool => $get('invoice_type') === 'monthly')
                        ->required(fn (Get $get): bool => $get('invoice_type') === 'monthly'),
                    Select::make('fee_type_id')
                        ->label('Fee Type')
                        ->options(fn () => FeeType::where('is_monthly', false)->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->native(false)
                        ->searchable()
                        ->visible(fn (Get $get): bool => $get('invoice_type') === 'one_time')
                        ->required(fn (Get $get): bool => $get('invoice_type') === 'one_time'),
                    Select::make('year')
                        ->label('Year')
                        ->options(fn () => collect(range(now()->year - 1, now()->year + 1))
                            ->mapWithKeys(fn (int $y) => [$y => $y]))
                        ->native(false)
                        ->default(now()->year)
                        ->required(),
                ])
                ->modalHeading('Generate Invoices — All Classes')
                ->modalDescription('This will generate invoices for every active class. Students who already have an invoice for the selected period will be skipped.')
                ->modalSubmitActionLabel('Generate')
                ->action(function (array $data): void {
                    if ($data['invoice_type'] === 'monthly') {
                        $result = app(GenerateMonthlyFeeInvoicesAction::class)
                            ->handle((int) $data['month'], (int) $data['year']);

                        $title = 'Monthly invoices generated';
                    } else {
                        $result = app(GenerateOneTimeFeeInvoicesForAllClassesAction::class)
                            ->handle((int) $data['fee_type_id'], (int) $data['year']);

                        $title = 'One-time invoices generated';
                    }

                    Notification::make()
                        ->title($title)
                        ->body("Generated: {$result['generated']} | Skipped (already exists): {$result['skipped']}")
                        ->success()
                        ->send();
                }),
            Action::make('copyToNewSession')
                ->label('Copy to New Session')
                ->icon('heroicon-o-document-duplicate')
                ->color('warning')
                ->fillForm(function (): array {
                    $latestYear = $this->sessionYears()->first();

                    return [
                        'from_year' => $latestYear,
                        'to_year' => $latestYear ? $latestYear + 1 : null,
                    ];
                })
                ->schema([
                    Select::make('from_year')
                        ->label('Copy From Session Year')
                        ->options(fn (): array => $this->sessionYears()->mapWithKeys(fn (int $year): array => [$year => $year])->all())
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
                ->modalHeading('Copy to New Session — All Classes')
                ->modalDescription('সব ক্লাসের নির্বাচিত সেশনের সব active fee structure নতুন সেশনে কপি হবে। পুরনো সেশনের structure যেমন আছে তেমনই থাকবে।')
                ->modalSubmitActionLabel('Copy')
                ->action(function (array $data): void {
                    $result = app(CopyFeeStructuresToSessionYearAction::class)
                        ->handle((int) $data['from_year'], (int) $data['to_year']);

                    Notification::make()
                        ->title('Fee structures copied')
                        ->body("Copied: {$result['copied']} | Skipped (already exists in {$data['to_year']}): {$result['skipped']}")
                        ->success()
                        ->send();
                }),
            Action::make('create')
                ->label('Add Fee Structure')
                ->url(FeeStructureResource::getUrl('create'))
                ->icon('heroicon-o-plus'),
        ];
    }

    /**
     * @return Collection<int, int>
     */
    private function sessionYears(): Collection
    {
        return FeeStructure::query()
            ->distinct()
            ->orderByDesc('session_year')
            ->pluck('session_year');
    }
}
