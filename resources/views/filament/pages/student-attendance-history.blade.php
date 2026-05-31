@assets
<style>
    body:has(.attendance-history-page) .fi-page-header-main-ctn {
        row-gap: calc(var(--spacing) * 3);
    }
</style>
@endassets

<x-filament-panels::page>
    <div class="attendance-history-page space-y-3">

        @php $history = $this->getHistory(); @endphp

        {{-- Filter Card --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">

            {{-- Header row: back button + title + count --}}
            <div class="flex items-center gap-x-2 border-b border-gray-100 px-3 py-2.5 dark:border-gray-800">
                <a href="{{ route('filament.admin.pages.mark-student-attendance') }}?classId={{ $classId }}">
                    <x-filament::icon-button icon="heroicon-o-arrow-left" color="gray" size="sm" label="Back" />
                </a>
                <x-heroicon-o-funnel class="h-4 w-4 text-primary-500" />
                <span class="text-sm font-semibold text-gray-900 dark:text-white">Filter by Date Range</span>
                <div class="ml-auto">
                    <x-filament::badge color="primary">
                        {{ $history->count() }} {{ Str::plural('day', $history->count()) }} found
                    </x-filament::badge>
                </div>
            </div>

            {{-- Inputs row --}}
            <div class="flex flex-col gap-2 px-3 py-2 sm:flex-row sm:items-center">

                {{-- Date inputs: always in one row --}}
                <div class="flex flex-1 items-center gap-2">
                    <span class="shrink-0 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">From</span>
                    <input
                        type="date"
                        wire:model="fromDate"
                        class="min-w-0 flex-1 rounded-lg border-gray-300 py-1.5 px-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    >
                    <x-heroicon-o-arrow-right class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                    <span class="shrink-0 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">To</span>
                    <input
                        type="date"
                        wire:model="toDate"
                        class="min-w-0 flex-1 rounded-lg border-gray-300 py-1.5 px-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    >
                </div>

                {{-- Buttons: full width on mobile, auto on desktop --}}
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

        {{-- Date Cards --}}
        @if ($history->isEmpty())
            <x-filament::empty-state icon="heroicon-o-calendar-days">
                <x-slot name="heading">No attendance records found for this date range.</x-slot>
            </x-filament::empty-state>
        @else
            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                @foreach ($history as $record)
                    <a
                        href="{{ route('filament.admin.pages.mark-student-attendance') }}?classId={{ $classId }}&date={{ \Carbon\Carbon::parse($record->date)->toDateString() }}"
                        class="group flex flex-col gap-y-2 rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-950/5 transition-all duration-150 hover:shadow-md dark:bg-gray-900 dark:ring-white/10"
                    >
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

                        {{-- Stats --}}
                        <div class="space-y-1">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-x-1">
                                    <div class="h-2 w-2 rounded-full bg-success-500"></div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Present</span>
                                </div>
                                <span class="text-sm font-bold text-success-600 dark:text-success-400">{{ $record->present_count }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-x-1">
                                    <div class="h-2 w-2 rounded-full bg-danger-500"></div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Absent</span>
                                </div>
                                <span class="text-sm font-bold text-danger-600 dark:text-danger-400">{{ $record->absent_count }}</span>
                            </div>
                            <div class="flex items-center justify-between border-t border-gray-100 pt-1 dark:border-gray-800">
                                <span class="text-xs text-gray-400 dark:text-gray-500">Total</span>
                                <span class="text-xs font-medium text-gray-600 dark:text-gray-300">{{ $record->total }}</span>
                            </div>
                        </div>

                        <x-heroicon-o-arrow-right
                            class="h-3.5 w-3.5 self-end text-gray-300 transition-colors group-hover:text-primary-500 dark:text-gray-600 dark:group-hover:text-primary-400"
                        />
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
