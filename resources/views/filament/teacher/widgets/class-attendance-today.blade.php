<x-filament-widgets::widget>
    <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-4">
        <h2 class="mb-3 flex items-center gap-x-1.5 text-sm font-semibold text-gray-900 dark:text-white sm:text-base">
            <x-heroicon-o-clipboard-document-check class="h-4 w-4 text-primary-500 sm:h-5 sm:w-5" />
            আজকের উপস্থিতি — আমার ক্লাস
        </h2>

        <div class="flex flex-col gap-2">
            @foreach ($classes as $class)
                <a
                    href="{{ $markUrl }}?classId={{ $class->id }}"
                    class="flex items-center justify-between gap-x-2 rounded-lg border border-gray-200 p-2.5 transition-colors duration-150 hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5 sm:p-3"
                >
                    <div class="min-w-0">
                        <p class="truncate text-xs font-semibold text-gray-900 dark:text-white sm:text-sm">{{ $class->name }}</p>
                        @if ($class->is_marked)
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                <span class="text-success-600 dark:text-success-400">উপস্থিত {{ $class->present_today }}</span>
                                ·
                                <span class="text-danger-600 dark:text-danger-400">অনুপস্থিত {{ $class->absent_today }}</span>
                            </p>
                        @else
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $class->student_profiles_count }} জন শিক্ষার্থী</p>
                        @endif
                    </div>

                    @if ($class->is_marked)
                        <span class="flex shrink-0 items-center gap-x-1 rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-950 dark:text-success-300">
                            <x-heroicon-o-check-circle class="h-3.5 w-3.5" />
                            নেওয়া হয়েছে
                        </span>
                    @else
                        <span class="flex shrink-0 items-center gap-x-1 rounded-full bg-warning-50 px-2 py-0.5 text-xs font-medium text-warning-700 dark:bg-warning-950 dark:text-warning-300">
                            <x-heroicon-o-exclamation-triangle class="h-3.5 w-3.5" />
                            বাকি
                        </span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
