<x-filament-panels::page>
    <div class="space-y-3">

        {{-- Student Info Card --}}
        @if ($student)
            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center gap-x-4 px-4 py-3">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary-50 dark:bg-primary-950">
                        <x-heroicon-o-user class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-base font-semibold text-gray-900 dark:text-white">
                            {{ $student->user?->name ?? '—' }}
                        </p>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            @if ($student->roll_no)
                                <x-filament::badge color="gray" size="sm">Roll: {{ $student->roll_no }}</x-filament::badge>
                            @endif
                            @if ($student->class)
                                <x-filament::badge color="primary" size="sm">{{ $student->class->name }}</x-filament::badge>
                            @endif
                            <x-filament::badge :color="$student->status->getColor()" size="sm">
                                {{ $student->status->getLabel() }}
                            </x-filament::badge>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 gap-3">

            {{-- Present --}}
            <div class="flex items-center gap-x-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-success-50 dark:bg-success-950">
                    <x-heroicon-o-check-circle class="h-5 w-5 text-success-600 dark:text-success-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold tracking-tight text-success-600 dark:text-success-400">{{ $presentCount }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Present</p>
                </div>
            </div>

            {{-- Absent --}}
            <div class="flex items-center gap-x-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-danger-50 dark:bg-danger-950">
                    <x-heroicon-o-x-circle class="h-5 w-5 text-danger-600 dark:text-danger-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold tracking-tight text-danger-600 dark:text-danger-400">{{ $absentCount }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Absent</p>
                </div>
            </div>
        </div>

        {{-- Filter Card --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">

            <div class="flex items-center gap-x-2 border-b border-gray-100 px-3 py-2.5 dark:border-gray-800">
                <x-heroicon-o-funnel class="h-4 w-4 text-primary-500" />
                <span class="text-sm font-semibold text-gray-900 dark:text-white">Filter by Date Range</span>
                <div class="ml-auto">
                    <x-filament::badge color="primary">
                        {{ $records->count() }} {{ Str::plural('day', $records->count()) }} found
                    </x-filament::badge>
                </div>
            </div>

            <div class="flex flex-col gap-2 px-3 py-2 sm:flex-row sm:items-center">
                <div class="flex flex-1 items-center gap-2">
                    <span class="shrink-0 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">From</span>
                    <input
                        type="date"
                        wire:model="fromDate"
                        class="min-w-0 flex-1 rounded-lg border-gray-300 px-2 py-1.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    >
                    <x-heroicon-o-arrow-right class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                    <span class="shrink-0 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">To</span>
                    <input
                        type="date"
                        wire:model="toDate"
                        class="min-w-0 flex-1 rounded-lg border-gray-300 px-2 py-1.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    >
                </div>
                <div class="flex gap-2">
                    <x-filament::button icon="heroicon-o-magnifying-glass" size="sm" wire:click="$refresh" class="flex-1 sm:flex-none">
                        Search
                    </x-filament::button>
                    <x-filament::button color="gray" icon="heroicon-o-arrow-path" size="sm" wire:click="resetDateRange" class="flex-1 sm:flex-none">
                        Last 30 Days
                    </x-filament::button>
                </div>
            </div>
        </div>

        {{-- Attendance Records --}}
        @if ($records->isEmpty())
            <x-filament::empty-state icon="heroicon-o-calendar-days">
                <x-slot name="heading">No attendance records found for this date range.</x-slot>
            </x-filament::empty-state>
        @else
            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                @foreach ($records as $record)
                    <div class="flex flex-col gap-y-2.5 rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">

                        {{-- Date --}}
                        <div class="flex items-center gap-x-2">
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-primary-50 dark:bg-primary-950">
                                <x-heroicon-o-calendar-days class="h-3.5 w-3.5 text-primary-600 dark:text-primary-400" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-semibold text-gray-900 dark:text-white">
                                    {{ \Carbon\Carbon::parse($record->date)->format('d M Y') }}
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-500">
                                    {{ \Carbon\Carbon::parse($record->date)->format('l') }}
                                </p>
                            </div>
                        </div>

                        {{-- Status --}}
                        <x-filament::badge
                            :color="$record->status->getColor()"
                            :icon="$record->status->getIcon()"
                            size="sm"
                        >
                            {{ $record->status->getLabel() }}
                        </x-filament::badge>
                    </div>
                @endforeach
            </div>
        @endif

    </div>
</x-filament-panels::page>
