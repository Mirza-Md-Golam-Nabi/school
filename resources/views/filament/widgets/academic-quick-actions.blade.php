@php
    $colorClasses = [
        'primary' => 'bg-primary-50 text-primary-600 dark:bg-primary-950 dark:text-primary-400',
        'success' => 'bg-success-50 text-success-600 dark:bg-success-950 dark:text-success-400',
        'info' => 'bg-info-50 text-info-600 dark:bg-info-950 dark:text-info-400',
        'warning' => 'bg-warning-50 text-warning-600 dark:bg-warning-950 dark:text-warning-400',
        'gray' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
    ];
@endphp

<x-filament-widgets::widget>
    <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-4">
        <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white sm:text-base">{{ __('Quick Actions') }}</h2>

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-5">
            @foreach ($actions as $action)
                <a
                    href="{{ $action['url'] }}"
                    class="flex flex-col items-center justify-center gap-y-1.5 rounded-lg border border-gray-200 p-2.5 text-center transition-colors duration-150 hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5 sm:gap-y-2 sm:p-3"
                >
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg sm:h-11 sm:w-11 {{ $colorClasses[$action['color']] }}">
                        @svg($action['icon'], 'h-5 w-5 sm:h-6 sm:w-6')
                    </span>
                    <span class="text-xs font-medium text-gray-700 dark:text-gray-200 sm:text-sm">{{ $action['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
