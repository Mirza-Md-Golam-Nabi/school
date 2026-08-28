<?php

namespace App\Filament\Resources\FeeStructures\Pages;

use App\Actions\GenerateMonthlyFeeInvoicesAction;
use App\Actions\GenerateOneTimeFeeInvoicesForAllClassesAction;
use App\Filament\Resources\FeeStructures\FeeStructureResource;
use App\Models\Classes;
use App\Models\FeeType;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;

class ListFeeStructures extends Page
{
    protected static string $resource = FeeStructureResource::class;

    protected string $view = 'filament.resources.fee-structures.pages.list-fee-structures';

    public Collection $classes;

    public function mount(): void
    {
        $this->classes = Classes::with([
            'feeStructures' => fn ($q) => $q->where('is_active', true)->with('feeType'),
        ])
            ->withCount(['feeStructures' => fn ($q) => $q->where('is_active', true)])
            ->active()
            ->orderBy('order')
            ->get()
            ->each(function ($class) {
                $class->setRelation(
                    'feeStructures',
                    $class->feeStructures->sortBy('feeType.name')->values()
                );
            });
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
            Action::make('create')
                ->label('Add Fee Structure')
                ->url(FeeStructureResource::getUrl('create'))
                ->icon('heroicon-o-plus'),
        ];
    }
}
