<x-filament-widgets::widget>
    <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-4">
        <h2 class="mb-3 flex items-center gap-x-1.5 text-sm font-semibold text-gray-900 dark:text-white sm:text-base">
            <x-heroicon-o-calendar-days class="h-4 w-4 text-primary-500 sm:h-5 sm:w-5" />
            আসন্ন পরীক্ষার সময়সূচী
        </h2>

        @if ($exams->isEmpty())
            <div class="flex items-center gap-x-2 rounded-lg bg-gray-50 p-3 text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400 sm:text-sm">
                <x-heroicon-o-calendar class="h-5 w-5 shrink-0" />
                <span>আসন্ন কোনো পরীক্ষা নেই।</span>
            </div>
        @else
            <div class="grid grid-cols-1 gap-1">
                @foreach ($exams as $exam)
                    <div class="flex items-center gap-x-2 rounded-lg border border-gray-200 p-2.5 dark:border-white/10 sm:p-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950 dark:text-primary-400">
                            <x-heroicon-o-calendar-days class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-xs font-semibold text-gray-900 dark:text-white sm:text-sm">{{ $exam['label'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $exam['startDate'] }} — {{ $exam['endDate'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
