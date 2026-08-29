<x-filament-widgets::widget>
    <a
        href="{{ $url }}"
        @class([
            'block rounded-xl p-3 shadow-sm transition-shadow duration-150 hover:shadow-md sm:p-4',
            'border border-danger-400 bg-danger-50 dark:border-danger-700 dark:bg-danger-950' => $totalDue > 0,
            'bg-white ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10' => $totalDue <= 0,
        ])
    >
        <div class="flex items-center gap-x-2 sm:gap-x-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-950 sm:h-14 sm:w-14">
                <x-heroicon-o-banknotes class="h-5 w-5 text-primary-600 dark:text-primary-400 sm:h-7 sm:w-7" />
            </div>
            <div class="min-w-0">
                <p class="text-xs font-bold tracking-tight text-gray-900 dark:text-white sm:text-2xl md:text-3xl">৳{{ number_format($totalDue, 0) }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 sm:text-sm">{{ __('Total Due') }}</p>
            </div>
        </div>

        <div class="mt-3 flex items-center justify-between gap-x-2 border-t border-gray-200 pt-3 text-xs dark:border-white/10 sm:mt-4 sm:pt-3 sm:text-sm">
            <span class="truncate text-gray-500 dark:text-gray-400">{{ trans_choice(':count pending invoice|:count pending invoices', $pendingCount, ['count' => $pendingCount]) }}</span>

            @if ($totalDiscount > 0)
                <span class="shrink-0 font-semibold text-success-600 dark:text-success-400">৳{{ number_format($totalDiscount, 0) }} {{ __('discount') }}</span>
            @endif
        </div>
    </a>
</x-filament-widgets::widget>
