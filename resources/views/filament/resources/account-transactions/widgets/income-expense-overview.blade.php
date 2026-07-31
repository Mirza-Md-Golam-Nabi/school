<x-filament-widgets::widget>
    @if ($isVisible)
        <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <p class="text-xs font-bold tracking-tight text-gray-900 dark:text-white sm:text-lg">
                    Income &amp; Expense Overview
                </p>

                <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center sm:gap-3">
                    <div class="min-w-0">
                        <label class="block text-xs text-gray-500 dark:text-gray-400">From</label>
                        <input
                            type="date"
                            wire:model.live="startDate"
                            class="fi-input mt-1 block w-full rounded-lg border-none bg-white text-xs text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20 sm:text-sm"
                        />
                    </div>

                    <div class="min-w-0">
                        <label class="block text-xs text-gray-500 dark:text-gray-400">To</label>
                        <input
                            type="date"
                            wire:model.live="endDate"
                            class="fi-input mt-1 block w-full rounded-lg border-none bg-white text-xs text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20 sm:text-sm"
                        />
                    </div>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-3 sm:mt-4">
                <a
                    href="{{ $incomeUrl }}"
                    wire:navigate
                    class="block rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 transition-shadow duration-150 hover:shadow-md dark:bg-gray-900 dark:ring-white/10 sm:p-4"
                >
                    <div class="flex items-center gap-x-2 sm:gap-x-3">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-success-50 dark:bg-success-950 sm:h-10 sm:w-10">
                            <x-heroicon-o-arrow-trending-up class="h-4 w-4 text-success-600 dark:text-success-400 sm:h-5 sm:w-5" />
                        </div>
                        <p class="truncate text-xs font-medium text-gray-500 dark:text-gray-400 sm:text-sm">
                            Income
                        </p>
                    </div>

                    <p class="mt-2 text-xs font-bold text-success-600 dark:text-success-400 sm:mt-3 sm:text-2xl">
                        ৳ {{ number_format($income, 2) }}
                    </p>
                </a>

                <a
                    href="{{ $expenseUrl }}"
                    wire:navigate
                    class="block rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 transition-shadow duration-150 hover:shadow-md dark:bg-gray-900 dark:ring-white/10 sm:p-4"
                >
                    <div class="flex items-center gap-x-2 sm:gap-x-3">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-danger-50 dark:bg-danger-950 sm:h-10 sm:w-10">
                            <x-heroicon-o-arrow-trending-down class="h-4 w-4 text-danger-600 dark:text-danger-400 sm:h-5 sm:w-5" />
                        </div>
                        <p class="truncate text-xs font-medium text-gray-500 dark:text-gray-400 sm:text-sm">
                            Expense
                        </p>
                    </div>

                    <p class="mt-2 text-xs font-bold text-danger-600 dark:text-danger-400 sm:mt-3 sm:text-2xl">
                        ৳ {{ number_format($expense, 2) }}
                    </p>
                </a>
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
