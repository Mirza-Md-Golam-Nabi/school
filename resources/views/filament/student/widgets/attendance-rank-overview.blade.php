<x-filament-widgets::widget>
    <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 transition-shadow duration-150 hover:shadow-md dark:bg-gray-900 dark:ring-white/10 sm:p-4">
        <div class="flex items-center gap-x-2 sm:gap-x-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-950 sm:h-14 sm:w-14">
                <x-heroicon-o-calendar-days class="h-5 w-5 text-primary-600 dark:text-primary-400 sm:h-7 sm:w-7" />
            </div>
            <div class="min-w-0">
                <p class="text-xs font-bold tracking-tight text-gray-900 dark:text-white sm:text-2xl md:text-3xl">{{ $presentDays }}/{{ $workingDays }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 sm:text-sm">Present Days</p>
            </div>
        </div>

        <div class="mt-3 flex items-center justify-between border-t border-gray-200 pt-3 dark:border-white/10 sm:mt-4 sm:pt-3">
            <div class="flex items-center gap-x-1.5">
                <x-heroicon-o-trophy class="h-4 w-4 shrink-0 text-warning-600 dark:text-warning-400" />
                <span class="text-xs text-gray-500 dark:text-gray-400 sm:text-sm">Your Rank</span>
            </div>
            <span class="text-xs font-bold text-primary-600 dark:text-primary-400 sm:text-2xl">
                {{ $rank ? '#'.$rank : '—' }}
            </span>
        </div>

        @if ($topStudent)
            <div class="mt-2 flex items-center justify-between gap-x-2 text-xs sm:text-sm">
                <span class="truncate text-gray-500 dark:text-gray-400">Top : {{ $topStudent['name'] }} ({{ $topStudent['roll_no'] }})</span>
                <span class="shrink-0 font-semibold text-success-600 dark:text-success-400">{{ $topStudent['present_count'] }} days</span>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
