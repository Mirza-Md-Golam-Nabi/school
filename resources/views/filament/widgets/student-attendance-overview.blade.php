<x-filament-widgets::widget>
    <a
        href="{{ $url }}"
        class="block rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 transition-shadow duration-150 hover:shadow-md dark:bg-gray-900 dark:ring-white/10 sm:p-4"
    >
        <div class="flex items-center gap-x-2 sm:gap-x-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-950 sm:h-14 sm:w-14">
                <x-heroicon-o-user-group class="h-5 w-5 text-primary-600 dark:text-primary-400 sm:h-7 sm:w-7" />
            </div>
            <div class="min-w-0">
                <p class="text-xl font-bold tracking-tight text-gray-900 dark:text-white md:text-3xl">{{ $totalStudents }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 sm:text-sm">Total Students</p>
            </div>
        </div>

        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 sm:divide-x divide-gray-200 border-t border-gray-200 pt-3 dark:divide-white/10 dark:border-white/10 sm:mt-4 sm:pt-3">
            <div class="flex items-center gap-x-1 text-xs sm:text-sm pb-2 sm:pb-0">
                <x-heroicon-o-check-circle class="h-4 w-4 shrink-0 text-success-600 dark:text-success-400" />
                <span class="truncate text-gray-500 dark:text-gray-400">Present :</span>
                <span class="font-semibold text-success-600 dark:text-success-400">{{ $presentToday }}</span>
            </div>
            <div class="flex items-center sm:justify-end gap-x-1 sm:pl-2 text-xs sm:text-sm">
                <x-heroicon-o-x-circle class="h-4 w-4 shrink-0 text-danger-600 dark:text-danger-400" />
                <span class="truncate text-gray-500 dark:text-gray-400">Absent :</span>
                <span class="font-semibold text-danger-600 dark:text-danger-400">{{ $absentToday }}</span>
            </div>
        </div>
    </a>
</x-filament-widgets::widget>