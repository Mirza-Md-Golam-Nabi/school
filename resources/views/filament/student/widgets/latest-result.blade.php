<x-filament-widgets::widget>
    <a
        href="{{ $url }}"
        wire:navigate
        class="block rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 transition-shadow duration-150 hover:shadow-md dark:bg-gray-900 dark:ring-white/10 sm:p-4"
    >
        <div class="flex items-center gap-x-2 sm:gap-x-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-success-50 dark:bg-success-950 sm:h-14 sm:w-14">
                <x-heroicon-o-trophy class="h-5 w-5 text-success-600 dark:text-success-400 sm:h-7 sm:w-7" />
            </div>
            <div class="min-w-0">
                <p class="text-xs font-bold tracking-tight text-gray-900 dark:text-white sm:text-2xl md:text-3xl">GPA {{ number_format((float) $gpa, 2) }}</p>
                <p class="truncate text-xs text-gray-500 dark:text-gray-400 sm:text-sm">{{ __('Latest Result — :exam', ['exam' => $examLabel]) }}</p>
            </div>
        </div>

        <div class="mt-3 flex items-center justify-between border-t border-gray-200 pt-3 dark:border-white/10 sm:mt-4 sm:pt-3">
            <div class="flex items-center gap-x-1.5">
                <x-heroicon-o-chart-bar class="h-4 w-4 shrink-0 text-primary-600 dark:text-primary-400" />
                <span class="text-xs text-gray-500 dark:text-gray-400 sm:text-sm">{{ __('Class Position') }}</span>
            </div>
            <span class="text-xs font-bold text-primary-600 dark:text-primary-400 sm:text-2xl">
                {{ $classRank ? '#'.$classRank : '—' }}{{ $totalStudents ? ' / '.$totalStudents : '' }}
            </span>
        </div>
    </a>
</x-filament-widgets::widget>
