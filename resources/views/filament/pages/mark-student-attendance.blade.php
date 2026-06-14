<x-filament-panels::page>
    <div class="space-y-3">

        {{-- Top Bar --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-2 px-3 py-2">

                {{-- Back --}}
                <a href="{{ route('filament.admin.pages.student-attendance') }}">
                    <x-filament::icon-button icon="heroicon-o-arrow-left" color="gray" size="sm" label="Back" />
                </a>

                {{-- Date + View History (always side by side, pushed to the right) --}}
                <div class="ml-auto flex items-center gap-2">
                    <div class="flex items-center gap-x-1.5 rounded-lg bg-gray-50 px-2.5 py-1.5 ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                        <x-heroicon-o-calendar-days class="h-4 w-4 shrink-0 text-primary-500" />
                        <label class="shrink-0 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Date</label>
                        <input
                            type="date"
                            wire:model.live="date"
                            class="w-[7.5rem] border-0 bg-transparent py-0 text-sm font-medium text-gray-900 focus:ring-0 dark:text-white"
                        >
                    </div>

                    <a href="{{ route('filament.admin.pages.student-attendance-history') }}?classId={{ $classId }}">
                        <x-filament::button color="gray" icon="heroicon-o-calendar-days" size="sm">
                            View History
                        </x-filament::button>
                    </a>
                </div>
            </div>

            {{-- Warning (only when not today) --}}
            @if ($date !== now()->toDateString())
                <div class="border-t border-gray-100 px-3 py-1.5 dark:border-gray-800">
                    <x-filament::badge color="warning" icon="heroicon-o-exclamation-triangle">
                        {{ $date < now()->toDateString() ? 'Past Date' : 'Future Date' }} — Admin will be notified
                    </x-filament::badge>
                </div>
            @endif
        </div>

        {{-- Student List --}}
        @php $students = $this->getStudents(); @endphp

        @if ($students->isEmpty())
            <x-filament::empty-state icon="heroicon-o-users">
                <x-slot name="heading">No students found in this class.</x-slot>
            </x-filament::empty-state>
        @else
            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">

                {{-- Header --}}
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-3 py-2.5 dark:border-gray-700">
                    <div class="flex items-center gap-x-1.5">
                        <x-heroicon-o-users class="h-4 w-4 text-gray-400" />
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            Students ({{ $students->count() }})
                        </span>
                    </div>
                    <div class="flex items-center gap-x-1.5">
                        <x-filament::button size="sm" color="gray" wire:click="selectAll">Select All</x-filament::button>
                        <x-filament::button size="sm" color="gray" wire:click="deselectAll">Deselect All</x-filament::button>
                    </div>
                </div>

                {{-- Student Checkboxes: 2 cols on mobile, 3 on tablet, 4 on lg --}}
                @php $yesterdayAttendance = $this->getYesterdayAttendance(); @endphp
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                    @foreach ($students as $student)
                        @php $yesterdayStatus = $yesterdayAttendance->get($student->id); @endphp
                        <label class="flex cursor-pointer items-center gap-x-2 border-b border-gray-100 px-3 py-2.5 transition-colors hover:bg-gray-50 last:border-0 dark:border-gray-800 dark:hover:bg-gray-800/50">
                            <input
                                type="checkbox"
                                wire:model.live="presentIds"
                                value="{{ $student->id }}"
                                class="h-4 w-4 shrink-0 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                            >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $student->user?->name ?? '—' }}
                                </p>
                                @if ($student->roll_no)
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Roll: {{ $student->roll_no }}</p>
                                @endif
                            </div>

                            {{-- Yesterday's attendance icon --}}
                            @if ($yesterdayStatus?->value === 'present')
                                <x-heroicon-o-check-circle class="h-4 w-4 shrink-0 text-success-500" title="Present yesterday" />
                            @elseif ($yesterdayStatus?->value === 'absent')
                                <x-heroicon-o-x-circle class="h-4 w-4 shrink-0 text-danger-500" title="Absent yesterday" />
                            @endif

                            <a
                                href="{{ route('filament.admin.pages.student-attendance-detail') }}?studentId={{ $student->id }}"
                                @click.stop
                                class="shrink-0 text-gray-300 transition-colors hover:text-primary-500 dark:text-gray-600 dark:hover:text-primary-400"
                                title="View Attendance History"
                            >
                                <x-heroicon-o-eye class="h-4 w-4 text-gray-900" />
                            </a>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Summary + Save --}}
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center gap-x-2">
                    <x-filament::badge color="success" icon="heroicon-o-check-circle">
                        Present: {{ count($presentIds) }}
                    </x-filament::badge>
                    <x-filament::badge color="danger" icon="heroicon-o-x-circle">
                        Absent: {{ $students->count() - count($presentIds) }}
                    </x-filament::badge>
                </div>
                <x-filament::button
                    wire:click="save"
                    wire:loading.attr="disabled"
                    icon="heroicon-o-check"
                    class="w-full sm:w-auto"
                >
                    <span wire:loading.remove wire:target="save">Save Attendance</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
