<x-filament-widgets::widget>
    <a
        href="{{ $url }}"
        wire:navigate
        class="block rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 transition-shadow duration-150 hover:shadow-md dark:bg-gray-900 dark:ring-white/10 sm:p-4"
    >
        <div class="flex items-center gap-x-2 sm:gap-x-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-950 sm:h-14 sm:w-14">
                <x-heroicon-o-user class="h-5 w-5 text-primary-600 dark:text-primary-400 sm:h-7 sm:w-7" />
            </div>
            <div class="min-w-0">
                <p class="truncate text-xs font-bold tracking-tight text-gray-900 dark:text-white sm:text-2xl">{{ $name }}</p>
                <p class="truncate text-xs text-gray-500 dark:text-gray-400 sm:text-sm">{{ $designation }}</p>
            </div>
        </div>

        @php
            $hasDepartment = filled($department);
            $hasStatus = filled($status);
        @endphp

        @if ($hasDepartment || $hasStatus)
            <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 border-t border-gray-200 pt-3 dark:border-white/10 sm:mt-4 sm:pt-3">
                @if ($hasDepartment)
                    <div class="flex items-center gap-x-1 text-xs sm:text-sm">
                        <x-heroicon-o-building-office-2 class="h-4 w-4 shrink-0 text-primary-600 dark:text-primary-400" />
                        <span class="truncate text-gray-500 dark:text-gray-400">{{ __('Dept') }} :</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $department }}</span>
                    </div>
                @endif

                @if ($hasStatus)
                    <div class="flex items-center gap-x-1 text-xs sm:text-sm">
                        <x-heroicon-o-check-badge class="h-4 w-4 shrink-0 text-primary-600 dark:text-primary-400" />
                        <span class="truncate text-gray-500 dark:text-gray-400">{{ __('Status') }} :</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $status->getLabel() }}</span>
                    </div>
                @endif
            </div>
        @endif
    </a>
</x-filament-widgets::widget>
