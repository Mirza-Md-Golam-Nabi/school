<x-filament-widgets::widget>
    <a
        href="{{ $url }}"
        class="block rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 transition-shadow duration-150 hover:shadow-md dark:bg-gray-900 dark:ring-white/10 sm:p-4"
    >
        <div class="flex items-center gap-x-2 sm:gap-x-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-warning-50 dark:bg-warning-950 sm:h-12 sm:w-12">
                <x-heroicon-o-banknotes class="h-5 w-5 text-warning-600 dark:text-warning-400 sm:h-6 sm:w-6" />
            </div>
            <div class="min-w-0">
                <p class="text-lg font-bold tracking-tight text-warning-600 dark:text-warning-400 sm:text-2xl">৳ {{ number_format($totalDue) }}</p>
                <p class="truncate text-xs text-gray-500 dark:text-gray-400 sm:text-sm">{{ __("My Classes' Due Fee") }}</p>
            </div>
        </div>

        <div class="mt-3 flex items-center gap-x-1 border-t border-gray-200 pt-3 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400 sm:text-sm">
            <x-heroicon-o-users class="h-4 w-4 shrink-0" />
            <span>{{ __(':count students have dues', ['count' => $studentsWithDues]) }}</span>
        </div>
    </a>
</x-filament-widgets::widget>
