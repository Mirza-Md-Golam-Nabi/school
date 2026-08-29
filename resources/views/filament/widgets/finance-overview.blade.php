<x-filament-widgets::widget>
    <div class="mb-2 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white sm:text-base">{{ __('Finance Overview') }}</h2>
        <span class="text-xs text-gray-500 dark:text-gray-400 sm:text-sm">{{ $monthLabel }}</span>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        {{-- Income --}}
        <a
            href="{{ $transactionsUrl }}"
            class="block rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 transition-shadow duration-150 hover:shadow-md dark:bg-gray-900 dark:ring-white/10 sm:p-4"
        >
            <div class="flex items-center gap-x-1.5 text-xs text-gray-500 dark:text-gray-400 sm:text-sm">
                <x-heroicon-o-arrow-trending-up class="h-4 w-4 text-success-500" />
                <span class="truncate">{{ __('This Month\'s Income') }}</span>
            </div>
            <p class="mt-1 text-base font-bold text-success-600 dark:text-success-400 sm:text-xl">৳ {{ number_format($income) }}</p>
        </a>

        {{-- Expense --}}
        <a
            href="{{ $transactionsUrl }}"
            class="block rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 transition-shadow duration-150 hover:shadow-md dark:bg-gray-900 dark:ring-white/10 sm:p-4"
        >
            <div class="flex items-center gap-x-1.5 text-xs text-gray-500 dark:text-gray-400 sm:text-sm">
                <x-heroicon-o-arrow-trending-down class="h-4 w-4 text-danger-500" />
                <span class="truncate">{{ __('This Month\'s Expense') }}</span>
            </div>
            <p class="mt-1 text-base font-bold text-danger-600 dark:text-danger-400 sm:text-xl">৳ {{ number_format($expense) }}</p>
        </a>

        {{-- Balance --}}
        <a
            href="{{ $transactionsUrl }}"
            class="block rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 transition-shadow duration-150 hover:shadow-md dark:bg-gray-900 dark:ring-white/10 sm:p-4"
        >
            <div class="flex items-center gap-x-1.5 text-xs text-gray-500 dark:text-gray-400 sm:text-sm">
                <x-heroicon-o-scale class="h-4 w-4 text-primary-500" />
                <span class="truncate">{{ __('Net Balance') }}</span>
            </div>
            <p @class([
                'mt-1 text-base font-bold sm:text-xl',
                'text-success-600 dark:text-success-400' => $balance >= 0,
                'text-danger-600 dark:text-danger-400' => $balance < 0,
            ])>৳ {{ number_format($balance) }}</p>
        </a>

        {{-- Total Due --}}
        <a
            href="{{ $duesUrl }}"
            class="block rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 transition-shadow duration-150 hover:shadow-md dark:bg-gray-900 dark:ring-white/10 sm:p-4"
        >
            <div class="flex items-center gap-x-1.5 text-xs text-gray-500 dark:text-gray-400 sm:text-sm">
                <x-heroicon-o-receipt-percent class="h-4 w-4 text-warning-500" />
                <span class="truncate">{{ __('Total Due Fee') }}</span>
            </div>
            <p class="mt-1 text-base font-bold text-warning-600 dark:text-warning-400 sm:text-xl">৳ {{ number_format($totalDue) }}</p>
        </a>
    </div>
</x-filament-widgets::widget>
