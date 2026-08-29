@php
    $colorClasses = [
        'danger' => 'bg-danger-50 text-danger-600 dark:bg-danger-950 dark:text-danger-400',
        'warning' => 'bg-warning-50 text-warning-600 dark:bg-warning-950 dark:text-warning-400',
        'info' => 'bg-info-50 text-info-600 dark:bg-info-950 dark:text-info-400',
    ];
@endphp

<x-filament-widgets::widget>
    <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-4">
        <h2 class="mb-3 flex items-center gap-x-1.5 text-sm font-semibold text-gray-900 dark:text-white sm:text-base">
            <x-heroicon-o-bell-alert class="h-4 w-4 text-danger-500 sm:h-5 sm:w-5" />
            {{ __('Urgent Tasks') }}
        </h2>

        @if (count($alerts) === 0)
            <div class="flex items-center gap-x-2 rounded-lg bg-success-50 p-3 text-xs text-success-700 dark:bg-success-950 dark:text-success-300 sm:text-sm">
                <x-heroicon-o-check-circle class="h-5 w-5 shrink-0" />
                <span>{{ __('All good — there are no urgent tasks right now.') }}</span>
            </div>
        @else
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($alerts as $alert)
                    <a
                        href="{{ $alert['url'] }}"
                        class="flex items-center gap-x-3 rounded-lg border border-gray-200 p-2.5 transition-colors duration-150 hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5 sm:p-3"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg sm:h-10 sm:w-10 {{ $colorClasses[$alert['color']] }}">
                            @svg($alert['icon'], 'h-5 w-5')
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400 sm:text-sm">{{ $alert['label'] }}</p>
                            <p class="text-sm font-bold text-gray-900 dark:text-white sm:text-base">{{ $alert['count'] }}</p>
                        </div>
                        <x-heroicon-o-chevron-right class="h-4 w-4 shrink-0 text-gray-400" />
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
