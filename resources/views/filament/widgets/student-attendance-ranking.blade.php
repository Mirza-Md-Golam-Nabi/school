<x-filament-widgets::widget>
    <a
        href="{{ $url }}"
        class="block rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 transition-shadow duration-150 hover:shadow-md dark:bg-gray-900 dark:ring-white/10 sm:p-4"
    >
        <div class="flex items-center gap-x-2 sm:gap-x-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-950 sm:h-14 sm:w-14">
                <x-heroicon-o-trophy class="h-5 w-5 text-primary-600 dark:text-primary-400 sm:h-7 sm:w-7" />
            </div>
            <div class="min-w-0">
                <p class="text-xl font-bold tracking-tight text-gray-900 dark:text-white md:text-3xl">{{ $workingDays }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 sm:text-sm">Working Days ({{ $year }})</p>
            </div>
        </div>

        <div class="mt-3 border-t border-gray-200 pt-3 dark:border-white/10 sm:mt-4 sm:pt-3">
            <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Top Attendance</p>

            @forelse ($topStudents as $student)
                <div class="flex items-center justify-between gap-x-2 py-0.5 text-xs sm:text-sm">
                    <div class="flex min-w-0 items-center gap-x-1.5">
                        <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-warning-100 text-[10px] font-bold text-warning-700 dark:bg-warning-900 dark:text-warning-300">{{ $student['roll_no'] ?? '—' }}</span>
                        <span class="truncate text-gray-700 dark:text-gray-300">{{ $student['name'] }}</span>
                    </div>
                    <span class="shrink-0 font-semibold text-success-600 dark:text-success-400">{{ $student['present_count'] }}</span>
                </div>
            @empty
                <p class="text-xs text-gray-400 dark:text-gray-500">No attendance recorded yet.</p>
            @endforelse
        </div>
    </a>
</x-filament-widgets::widget>
