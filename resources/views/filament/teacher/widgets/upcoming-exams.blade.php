<x-filament-widgets::widget>
    <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-4">
        <h2 class="mb-3 flex items-center gap-x-1.5 text-sm font-semibold text-gray-900 dark:text-white sm:text-base">
            <x-heroicon-o-calendar-days class="h-4 w-4 text-primary-500 sm:h-5 sm:w-5" />
            {{ __('Upcoming Exams') }}
        </h2>

        @if ($exams->isEmpty())
            <div class="flex items-center gap-x-2 rounded-lg bg-gray-50 p-3 text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400 sm:text-sm">
                <x-heroicon-o-calendar class="h-5 w-5 shrink-0" />
                <span>{{ __('No upcoming exams.') }}</span>
            </div>
        @else
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($exams as $exam)
                    <a
                        href="{{ $exam['url'] }}"
                        class="flex items-center gap-x-2 rounded-lg border border-gray-200 p-2.5 transition-colors duration-150 hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5 sm:p-3"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950 dark:text-primary-400">
                            <x-heroicon-o-calendar-days class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-semibold text-gray-900 dark:text-white sm:text-sm">{{ $exam['label'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $exam['date'] }}</p>
                        </div>
                        @if ($exam['isPublished'])
                            <span class="shrink-0 rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-950 dark:text-success-300">{{ __('Published') }}</span>
                        @else
                            <span class="shrink-0 rounded-full bg-warning-50 px-2 py-0.5 text-xs font-medium text-warning-700 dark:bg-warning-950 dark:text-warning-300">{{ __('Draft') }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
