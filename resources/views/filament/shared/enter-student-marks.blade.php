<x-filament-panels::page>
    @php
        $config = $this->getSubjectConfig();
        $students = $this->getStudents();
        $hasMcq = $config?->mcq_total !== null;
        $hasWritten = $config?->written_total !== null;
        $hasPractical = $config?->practical_total !== null;
    @endphp

    <div class="space-y-3">

        {{-- Top Bar --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-wrap items-center gap-2 px-3 py-2.5">
                <a href="{{ $this->getBackUrl() }}">
                    <x-filament::icon-button icon="heroicon-o-arrow-left" color="gray" size="sm" label="Back" />
                </a>
                <div class="flex flex-wrap items-center gap-2 ml-auto">
                    @if ($config)
                        @if ($hasMcq)
                            <x-filament::badge color="info">MCQ / {{ $config->mcq_total }}</x-filament::badge>
                        @endif
                        @if ($hasWritten)
                            <x-filament::badge color="warning">Written / {{ $config->written_total }}</x-filament::badge>
                        @endif
                        @if ($hasPractical)
                            <x-filament::badge color="success">Practical / {{ $config->practical_total }}</x-filament::badge>
                        @endif
                        <x-filament::badge color="primary">Total: {{ $config->total_marks }}</x-filament::badge>
                        <x-filament::badge color="danger">Pass: {{ $config->pass_mark }}</x-filament::badge>
                    @endif
                </div>
            </div>
        </div>

        @if ($students->isEmpty())
            <x-filament::empty-state icon="heroicon-o-users">
                <x-slot name="heading">এই ক্লাসে কোনো active student নেই।</x-slot>
            </x-filament::empty-state>
        @else
            {{-- Student Marks Table --}}
            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">

                {{-- Header --}}
                <div class="flex items-center gap-x-1.5 border-b border-gray-200 px-4 py-2.5 dark:border-gray-700">
                    <x-heroicon-o-users class="h-4 w-4 text-gray-400" />
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">
                        Students ({{ $students->count() }})
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50">
                                <th class="w-16 px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Roll</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Student</th>
                                @if ($hasMcq)
                                    <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        MCQ
                                        <span class="block text-gray-400 normal-case font-normal">/ {{ $config->mcq_total }}</span>
                                    </th>
                                @endif
                                @if ($hasWritten)
                                    <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        Written
                                        <span class="block text-gray-400 normal-case font-normal">/ {{ $config->written_total }}</span>
                                    </th>
                                @endif
                                @if ($hasPractical)
                                    <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        Practical
                                        <span class="block text-gray-400 normal-case font-normal">/ {{ $config->practical_total }}</span>
                                    </th>
                                @endif
                                <th class="px-4 py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Total</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Absent</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($students as $student)
                                @php
                                    $studentMark = $marks[$student->id] ?? ['mcq_marks' => null, 'written_marks' => null, 'practical_marks' => null, 'is_absent' => false];
                                    $isAbsent = (bool) ($studentMark['is_absent'] ?? false);
                                    $computedTotal = $isAbsent
                                        ? null
                                        : ((int) ($studentMark['mcq_marks'] ?? 0) + (int) ($studentMark['written_marks'] ?? 0) + (int) ($studentMark['practical_marks'] ?? 0));
                                @endphp
                                <tr class="border-b border-gray-100 transition-colors hover:bg-gray-50 last:border-0 dark:border-gray-800 dark:hover:bg-gray-800/30 {{ $isAbsent ? 'bg-danger-50/30 dark:bg-danger-900/10' : '' }}"
                                    wire:key="student-{{ $student->id }}">
                                    <td class="px-4 py-2.5 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $student->roll_no }}
                                    </td>
                                    <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-white">
                                        {{ $student->user?->name ?? '—' }}
                                    </td>

                                    @if ($hasMcq)
                                        <td class="px-3 py-1.5 text-center">
                                            <input
                                                type="number"
                                                min="0"
                                                max="{{ $config->mcq_total }}"
                                                step="0.1"
                                                wire:model.lazy="marks.{{ $student->id }}.mcq_marks"
                                                @disabled($isAbsent)
                                                class="w-20 rounded-lg border border-gray-400 bg-white px-2 py-1.5 text-center text-sm shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-400 dark:border-gray-500 dark:bg-gray-800 dark:text-white dark:disabled:border-gray-700 dark:disabled:bg-gray-900 dark:disabled:text-gray-600"
                                            >
                                        </td>
                                    @endif

                                    @if ($hasWritten)
                                        <td class="px-3 py-1.5 text-center">
                                            <input
                                                type="number"
                                                min="0"
                                                max="{{ $config->written_total }}"
                                                step="0.1"
                                                wire:model.lazy="marks.{{ $student->id }}.written_marks"
                                                @disabled($isAbsent)
                                                class="w-20 rounded-lg border border-gray-400 bg-white px-2 py-1.5 text-center text-sm shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-400 dark:border-gray-500 dark:bg-gray-800 dark:text-white dark:disabled:border-gray-700 dark:disabled:bg-gray-900 dark:disabled:text-gray-600"
                                            >
                                        </td>
                                    @endif

                                    @if ($hasPractical)
                                        <td class="px-3 py-1.5 text-center">
                                            <input
                                                type="number"
                                                min="0"
                                                max="{{ $config->practical_total }}"
                                                step="0.1"
                                                wire:model.lazy="marks.{{ $student->id }}.practical_marks"
                                                @disabled($isAbsent)
                                                class="w-20 rounded-lg border border-gray-400 bg-white px-2 py-1.5 text-center text-sm shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-400 dark:border-gray-500 dark:bg-gray-800 dark:text-white dark:disabled:border-gray-700 dark:disabled:bg-gray-900 dark:disabled:text-gray-600"
                                            >
                                        </td>
                                    @endif

                                    <td class="px-4 py-2.5 text-center">
                                        @if ($isAbsent)
                                            <span class="text-sm font-semibold text-danger-600 dark:text-danger-400">Absent</span>
                                        @else
                                            <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                                {{ $computedTotal > 0 ? $computedTotal : '—' }}
                                            </span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-2.5 text-center">
                                        <input
                                            type="checkbox"
                                            wire:model.live="marks.{{ $student->id }}.is_absent"
                                            class="h-4 w-4 rounded border-gray-300 text-danger-600 focus:ring-danger-500 dark:border-gray-600"
                                        >
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Save Footer --}}
            <div class="flex items-center justify-between rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-wrap gap-2">
                    @php
                        $absentCount = collect($marks)->filter(fn ($m) => $m['is_absent'] ?? false)->count();
                        $presentCount = $students->count() - $absentCount;
                    @endphp
                    <x-filament::badge color="success" icon="heroicon-o-check-circle">
                        Present: {{ $presentCount }}
                    </x-filament::badge>
                    <x-filament::badge color="danger" icon="heroicon-o-x-circle">
                        Absent: {{ $absentCount }}
                    </x-filament::badge>
                </div>
                <x-filament::button
                    wire:click="save"
                    wire:loading.attr="disabled"
                    icon="heroicon-o-check"
                >
                    <span wire:loading.remove wire:target="save">Save Marks</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
