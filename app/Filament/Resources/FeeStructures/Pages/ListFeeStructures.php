<?php

namespace App\Filament\Resources\FeeStructures\Pages;

use App\Actions\GenerateMonthlyFeeInvoicesAction;
use App\Filament\Resources\FeeStructures\FeeStructureResource;
use App\Models\Classes;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
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
            Action::make('generateMonthlyInvoices')
                ->label('Generate Monthly Invoices')
                ->icon('heroicon-o-document-plus')
                ->color('success')
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
                ->modalHeading('Generate Monthly Invoices — All Classes')
                ->modalDescription('This will generate the selected month\'s invoices for every active class with monthly fee structures. Students who already have an invoice for that month/year will be skipped.')
                ->modalSubmitActionLabel('Generate')
                ->action(function (array $data): void {
                    $result = app(GenerateMonthlyFeeInvoicesAction::class)
                        ->handle((int) $data['month'], (int) $data['year']);

                    Notification::make()
                        ->title('Monthly invoices generated')
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
