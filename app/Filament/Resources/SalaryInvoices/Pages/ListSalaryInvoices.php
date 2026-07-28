<?php

namespace App\Filament\Resources\SalaryInvoices\Pages;

use App\Actions\CreateManualSalaryInvoiceAction;
use App\Actions\GenerateMonthlySalaryInvoicesAction;
use App\Filament\Resources\Concerns\HasProfileableFields;
use App\Filament\Resources\Concerns\ResponsiveText;
use App\Filament\Resources\SalaryInvoices\SalaryInvoiceResource;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSalaryInvoices extends ListRecords
{
    protected static string $resource = SalaryInvoiceResource::class;

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
                        ->required()
                        ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                    Select::make('year')
                        ->label('Year')
                        ->options(fn () => collect(range(now()->year - 1, now()->year + 1))
                            ->mapWithKeys(fn (int $y) => [$y => $y]))
                        ->native(false)
                        ->default(now()->year)
                        ->required()
                        ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                ])
                ->modalHeading('Generate Monthly Invoices — সব active Teacher/Staff')
                ->modalDescription('এই মাসের salary invoice সব active teacher/staff-এর জন্য জেনারেট হবে (যাদের effective salary structure আছে)। যাদের এই মাসের invoice আগে থেকেই আছে তারা স্কিপ হবে।')
                ->modalSubmitActionLabel('Generate')
                ->action(function (array $data): void {
                    $result = app(GenerateMonthlySalaryInvoicesAction::class)
                        ->handle((int) $data['month'], (int) $data['year'], auth()->id());

                    Notification::make()
                        ->title('Monthly invoices generated')
                        ->body("Generated: {$result['generated']} | Skipped (already exists): {$result['skipped']} | No structure: {$result['no_structure']}")
                        ->success()
                        ->send();
                }),

            Action::make('createManualInvoice')
                ->label('Create Manual Invoice')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->schema(function () {
                    [$profileableType, $profileableId] = HasProfileableFields::fields();

                    return [
                        $profileableType,
                        $profileableId,
                        Select::make('month')
                            ->label('Month')
                            ->options(fn () => collect(range(1, 12))
                                ->mapWithKeys(fn (int $m) => [$m => Carbon::create()->month($m)->format('F')]))
                            ->native(false)
                            ->default(now()->month)
                            ->required()
                            ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                        Select::make('year')
                            ->label('Year')
                            ->options(fn () => collect(range(now()->year - 1, now()->year + 1))
                                ->mapWithKeys(fn (int $y) => [$y => $y]))
                            ->native(false)
                            ->default(now()->year)
                            ->required()
                            ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                        TextInput::make('net_amount')
                            ->label('Net Amount')
                            ->numeric()
                            ->minValue(0.01)
                            ->prefix('৳')
                            ->required()
                            ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                    ];
                })
                ->modalHeading('Create Manual Invoice')
                ->modalDescription('Mid-month joining, resigned/inactive staff, অথবা অন্য কোনো বিশেষ কারণে flat amount দিয়ে invoice তৈরি করতে এটা ব্যবহার করুন।')
                ->modalSubmitActionLabel('Create')
                ->action(function (array $data): void {
                    try {
                        app(CreateManualSalaryInvoiceAction::class)->handle(
                            $data['profileable_type'],
                            (int) $data['profileable_id'],
                            (int) $data['month'],
                            (int) $data['year'],
                            (float) $data['net_amount'],
                            auth()->id(),
                        );

                        Notification::make()
                            ->title('Invoice created')
                            ->success()
                            ->send();
                    } catch (\RuntimeException $exception) {
                        Notification::make()
                            ->title('তৈরি করা যায়নি')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
