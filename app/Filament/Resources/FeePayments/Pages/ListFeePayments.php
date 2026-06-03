<?php

namespace App\Filament\Resources\FeePayments\Pages;

use App\Filament\Resources\FeePayments\FeePaymentResource;
use App\Models\Classes;
use App\Models\FeePayment;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class ListFeePayments extends Page
{
    protected static string $resource = FeePaymentResource::class;

    protected string $view = 'filament.resources.fee-payments.pages.list-fee-payments';

    public Collection $classes;

    public function mount(): void
    {
        $this->classes = Classes::where('is_active', true)
            ->orderBy('order')
            ->get()
            ->each(function ($class) {
                $stats = FeePayment::whereHas(
                    'student', fn ($q) => $q->where('current_class_id', $class->id)
                )
                    ->selectRaw('payment_method, COUNT(*) as count, COALESCE(SUM(amount_paid), 0) as total_amount')
                    ->groupBy('payment_method')
                    ->get()
                    ->keyBy(fn ($item) => $item->getRawOriginal('payment_method'));

                $class->paymentStats = $stats;
                $class->total_payments = $stats->sum('count');
                $class->total_collected = $stats->sum('total_amount');
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('New Payment')
                ->url(FeePaymentResource::getUrl('create'))
                ->icon('heroicon-o-plus'),
        ];
    }
}
