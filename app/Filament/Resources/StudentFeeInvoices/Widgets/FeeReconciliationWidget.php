<?php

namespace App\Filament\Resources\StudentFeeInvoices\Widgets;

use App\Actions\CalculateFeeReconciliationAction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FeeReconciliationWidget extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = [
        'default' => 'full',
    ];

    protected int|array|null $columns = [
        'default' => 2,
        'md' => 3,
        'xl' => 5,
    ];

    protected ?string $heading = 'Fee হিসাব (সব সময়ের)';

    protected ?string $description = 'মোট বিল = আদায় + মওকুফ + Default + আদায়যোগ্য বকেয়া';

    protected function getStats(): array
    {
        $summary = app(CalculateFeeReconciliationAction::class)->handle();

        $compact = ['class' => '!py-3 !px-3 sm:!px-4'];

        return [
            Stat::make('মোট বিল', self::money($summary['billed']))
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->extraAttributes($compact),

            Stat::make('আদায়', self::money($summary['collected']))
                ->description('Account-এ জমা হয়েছে')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->extraAttributes($compact),

            Stat::make('মওকুফ', self::money($summary['waived']))
                ->icon('heroicon-o-minus-circle')
                ->color('gray')
                ->extraAttributes($compact),

            Stat::make('Default (অনাদায়ী)', self::money($summary['defaulted']))
                ->description('আদায়ের আশা নেই')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->extraAttributes($compact),

            Stat::make('আদায়যোগ্য বকেয়া', self::money($summary['receivable']))
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->extraAttributes($compact),
        ];
    }

    private static function money(float $amount): string
    {
        return '৳'.number_format($amount, 0);
    }
}
