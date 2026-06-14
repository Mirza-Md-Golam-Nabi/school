<x-filament-panels::page>

    {{-- Today's Summary Cards --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">

        {{-- Total --}}
        <div class="flex items-center gap-x-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800">
                <x-heroicon-o-user-group class="h-5 w-5 text-gray-600 dark:text-gray-400" />
            </div>
            <div>
                <p class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $totalStudents }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Total Students</p>
            </div>
        </div>

        {{-- Present --}}
        <div class="flex items-center gap-x-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-success-50 dark:bg-success-950">
                <x-heroicon-o-check-circle class="h-5 w-5 text-success-600 dark:text-success-400" />
            </div>
            <div>
                <p class="text-2xl font-bold tracking-tight text-success-600 dark:text-success-400">{{ $presentToday }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Present Today</p>
            </div>
        </div>

        {{-- Absent --}}
        <div class="flex items-center gap-x-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-danger-50 dark:bg-danger-950">
                <x-heroicon-o-x-circle class="h-5 w-5 text-danger-600 dark:text-danger-400" />
            </div>
            <div>
                <p class="text-2xl font-bold tracking-tight text-danger-600 dark:text-danger-400">{{ $absentToday }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Absent Today</p>
            </div>
        </div>

        {{-- Not Marked --}}
        <div class="flex items-center gap-x-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-warning-50 dark:bg-warning-950">
                <x-heroicon-o-clock class="h-5 w-5 text-warning-600 dark:text-warning-400" />
            </div>
            <div>
                <p class="text-2xl font-bold tracking-tight text-warning-600 dark:text-warning-400">{{ $notMarkedToday }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Not Marked</p>
            </div>
        </div>
    </div>

    {{-- Class Cards --}}
    @if ($classes->isEmpty())
        <x-filament::empty-state icon="heroicon-o-academic-cap">
            <x-slot name="heading">No active classes available.</x-slot>
        </x-filament::empty-state>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-5">
            @foreach ($classes as $class)
                <a
                    href="{{ route('filament.admin.pages.mark-student-attendance') }}?classId={{ $class->id }}"
                    class="group flex flex-col gap-y-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-950/5 transition-all duration-150 hover:shadow-md dark:bg-gray-900 dark:ring-white/10"
                >
                    <div class="flex items-center gap-x-2">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary-50 dark:bg-primary-950">
                            <x-heroicon-o-academic-cap class="h-4 w-4 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate text-xs font-semibold text-gray-900 dark:text-white">
                                {{ $class->name }}
                            </h3>
                            <x-filament::badge :color="$class->level->getColor()" size="sm">
                                {{ $class->level->getLabel() }}
                            </x-filament::badge>
                        </div>
                    </div>

                    {{-- Today's mini attendance summary --}}
                    @if ($class->is_marked)
                        <div class="flex flex-wrap items-center gap-1">
                            <x-filament::badge color="success" icon="heroicon-o-check-circle" size="sm">
                                Present: {{ $class->present_today }}
                            </x-filament::badge>
                            <x-filament::badge color="danger" icon="heroicon-o-x-circle" size="sm">
                                Absent: {{ $class->absent_today }}
                            </x-filament::badge>
                        </div>
                    @else
                        <x-filament::badge color="gray" size="sm">Not marked yet</x-filament::badge>
                    @endif

                    <div class="flex items-end justify-between">
                        <div>
                            <p class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                                {{ $class->student_profiles_count }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Students</p>
                        </div>
                        <x-heroicon-o-arrow-right
                            class="h-4 w-4 text-gray-300 transition-colors duration-150 group-hover:text-primary-500 dark:text-gray-600 dark:group-hover:text-primary-400"
                        />
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
