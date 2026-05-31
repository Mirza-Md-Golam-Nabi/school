<x-filament-panels::page>
    <div class="space-y-4">

        {{-- Date Picker --}}
        <div class="flex flex-wrap items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Date</label>
            <input
                type="date"
                wire:model.live="date"
                class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
            >

            @if ($date !== now()->toDateString())
                <x-filament::badge
                    color="warning"
                    icon="heroicon-o-exclamation-triangle"
                >
                    {{ $date < now()->toDateString() ? 'Past Date — Admin will be notified' : 'Future Date — Admin will be notified' }}
                </x-filament::badge>
            @endif
        </div>

        {{-- Teacher List --}}
        @php $teachers = $this->getTeachers(); @endphp

        @if ($teachers->isEmpty())
            <x-filament::empty-state icon="heroicon-o-users">
                <x-slot name="heading">No teachers found.</x-slot>
            </x-filament::empty-state>
        @else
            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                    <div class="flex items-center gap-x-2">
                        <x-heroicon-o-users class="h-5 w-5 text-gray-400" />
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            Teachers ({{ $teachers->count() }})
                        </span>
                    </div>
                    <div class="flex items-center gap-x-2">
                        <x-filament::button size="sm" color="gray" wire:click="selectAll">
                            Select All
                        </x-filament::button>
                        <x-filament::button size="sm" color="gray" wire:click="deselectAll">
                            Deselect All
                        </x-filament::button>
                    </div>
                </div>

                {{-- Teacher Checkboxes --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($teachers as $teacher)
                        <label
                            class="flex cursor-pointer items-center gap-x-3 border-b border-gray-100 px-4 py-3 transition-colors hover:bg-gray-50 last:border-0 dark:border-gray-800 dark:hover:bg-gray-800/50"
                        >
                            <input
                                type="checkbox"
                                wire:model.live="presentIds"
                                value="{{ $teacher->id }}"
                                class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                            >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $teacher->user?->name ?? '—' }}
                                </p>
                                @if ($teacher->designation)
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $teacher->designation }}</p>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Summary + Save --}}
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center gap-x-3">
                    <x-filament::badge color="success" icon="heroicon-o-check-circle">
                        Present: {{ count($presentIds) }}
                    </x-filament::badge>
                    <x-filament::badge color="danger" icon="heroicon-o-x-circle">
                        Absent: {{ $teachers->count() - count($presentIds) }}
                    </x-filament::badge>
                </div>
                <x-filament::button
                    wire:click="save"
                    wire:loading.attr="disabled"
                    icon="heroicon-o-check"
                >
                    <span wire:loading.remove wire:target="save">Save Attendance</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
