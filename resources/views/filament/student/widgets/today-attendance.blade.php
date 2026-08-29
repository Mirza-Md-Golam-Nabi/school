<x-filament-widgets::widget>
    <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 transition-shadow duration-150 hover:shadow-md dark:bg-gray-900 dark:ring-white/10 sm:p-4">
        <div class="flex items-center gap-x-2 sm:gap-x-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-950 sm:h-14 sm:w-14">
                <x-heroicon-o-clock class="h-5 w-5 text-primary-600 dark:text-primary-400 sm:h-7 sm:w-7" />
            </div>
            <div class="min-w-0">
                <p class="truncate text-xs font-bold tracking-tight text-gray-900 dark:text-white sm:text-lg">
                    {{ $isToday ? __("Today's Attendance") : __('Last Attendance') }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 sm:text-sm">{{ $date ?? '—' }}</p>
            </div>
        </div>

        <div class="mt-3 flex items-center justify-between border-t border-gray-200 pt-3 dark:border-white/10 sm:mt-4 sm:pt-3">
            @if ($status)
                <x-filament::badge :color="$status->getColor()" :icon="$status->getIcon()">
                    {{ $status->getLabel() }}
                </x-filament::badge>

                <span class="text-xs font-bold text-gray-900 dark:text-white sm:text-2xl">{{ $time ?? '—' }}</span>
            @else
                <x-filament::badge color="gray">{{ __('No attendance recorded yet') }}</x-filament::badge>
            @endif
        </div>
    </div>
</x-filament-widgets::widget>
