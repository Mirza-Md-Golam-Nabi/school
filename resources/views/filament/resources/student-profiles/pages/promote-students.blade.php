<x-filament-panels::page>
    @php
        $students = $this->getStudents();
        $targetClassOptions = $this->getTargetClassOptions();
        $sourceSessionYear = $students->first()?->session_year;
        $statusCounts = collect($promotions)->countBy('status');
    @endphp

    <div class="space-y-3">

        {{-- Top Bar --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-wrap items-center gap-2 px-3 py-2.5">
                <a href="{{ $this->getBackUrl() }}">
                    <x-filament::icon-button icon="heroicon-o-arrow-left" color="gray" size="sm" label="Back" />
                </a>
                @if ($sourceSessionYear)
                    <x-filament::badge color="gray" icon="heroicon-o-calendar-days">
                        Session {{ $sourceSessionYear }} &rarr; {{ $sourceSessionYear + 1 }}
                    </x-filament::badge>
                @endif
                <div class="ml-auto flex flex-wrap items-center gap-2">
                    @foreach (\App\Enums\PromotionStatus::cases() as $status)
                        <x-filament::badge :color="$status->getColor()">
                            {{ $status->getLabel() }}: {{ $statusCounts[$status->value] ?? 0 }}
                        </x-filament::badge>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($students->isEmpty())
            <x-filament::empty-state icon="heroicon-o-users">
                <x-slot name="heading">এই ক্লাসে কোনো active student নেই।</x-slot>
            </x-filament::empty-state>
        @else
            @unless ($hasMainExamResults)
                <div class="flex items-center gap-x-2 rounded-xl bg-warning-50 px-4 py-2.5 ring-1 ring-warning-600/20 dark:bg-warning-950 dark:ring-warning-400/20">
                    <x-heroicon-o-exclamation-triangle class="h-4 w-4 shrink-0 text-warning-600 dark:text-warning-400" />
                    <p class="text-xs text-warning-700 dark:text-warning-400">
                        এই ক্লাসের Main Exam-এর merit ranking পাওয়া যায়নি, তাই "New Roll" ফাঁকা আছে — প্রতিটা student-এর জন্য ম্যানুয়ালি roll বসাতে হবে।
                    </p>
                </div>
            @endunless

            {{-- Promotion Table --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">

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
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Target Class</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Section</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Group</th>
                                <th class="w-24 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">New Roll (Merit)</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($students as $student)
                                @php
                                    $row = $promotions[$student->id] ?? [];
                                    $isLeaving = in_array($row['status'] ?? null, ['graduated', 'transferred', 'dropped'], true);
                                    $sectionOptions = $this->getSectionOptions($row['class_id'] ?? null);
                                    $groupOptions = $this->getGroupOptions($row['class_id'] ?? null);
                                @endphp
                                <tr class="border-b border-gray-100 transition-colors hover:bg-gray-50 last:border-0 dark:border-gray-800 dark:hover:bg-gray-800/30 {{ $isLeaving ? 'bg-danger-50/30 dark:bg-danger-900/10' : '' }}"
                                    wire:key="student-{{ $student->id }}">
                                    <td class="px-4 py-2.5 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $student->roll_no }}
                                    </td>
                                    <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-white">
                                        {{ $student->user?->name ?? '—' }}
                                    </td>

                                    <td class="px-3 py-1.5">
                                        <select
                                            wire:model.live="promotions.{{ $student->id }}.status"
                                            class="w-32 rounded-lg border-gray-300 py-1.5 px-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                            @foreach (\App\Enums\PromotionStatus::cases() as $status)
                                                <option value="{{ $status->value }}">{{ $status->getLabel() }}</option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td class="px-3 py-1.5">
                                        <select
                                            wire:model.live="promotions.{{ $student->id }}.class_id"
                                            @disabled($isLeaving)
                                            class="w-36 rounded-lg border-gray-300 py-1.5 px-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:disabled:bg-gray-900">
                                            <option value="">—</option>
                                            @foreach ($targetClassOptions as $id => $name)
                                                <option value="{{ $id }}">{{ $name }}</option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td class="px-3 py-1.5">
                                        <select
                                            wire:model="promotions.{{ $student->id }}.section_id"
                                            @disabled($isLeaving || $sectionOptions->isEmpty())
                                            class="w-32 rounded-lg border-gray-300 py-1.5 px-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:disabled:bg-gray-900">
                                            <option value="">—</option>
                                            @foreach ($sectionOptions as $id => $name)
                                                <option value="{{ $id }}">{{ $name }}</option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td class="px-3 py-1.5">
                                        <select
                                            wire:model="promotions.{{ $student->id }}.group_id"
                                            @disabled($isLeaving || $groupOptions->isEmpty())
                                            class="w-32 rounded-lg border-gray-300 py-1.5 px-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:disabled:bg-gray-900">
                                            <option value="">—</option>
                                            @foreach ($groupOptions as $id => $name)
                                                <option value="{{ $id }}">{{ $name }}</option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td class="px-3 py-1.5">
                                        @if ($isLeaving)
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                @if ($rank = $this->meritRanks->get($student->id))
                                                    Final Rank: {{ $rank }}
                                                @else
                                                    —
                                                @endif
                                            </span>
                                        @else
                                            <input
                                                type="number"
                                                min="1"
                                                wire:model="promotions.{{ $student->id }}.roll_no"
                                                class="w-20 rounded-lg border border-gray-400 bg-white px-2 py-1.5 text-center text-sm shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-500 dark:bg-gray-800 dark:text-white">
                                        @endif
                                    </td>

                                    <td class="px-3 py-1.5">
                                        <input
                                            type="text"
                                            wire:model="promotions.{{ $student->id }}.remarks"
                                            placeholder="Optional"
                                            class="w-36 rounded-lg border border-gray-400 bg-white px-2 py-1.5 text-sm shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-500 dark:bg-gray-800 dark:text-white">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Submit Footer --}}
            <div class="flex items-center justify-between rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    "Repeated" status বেছে নিলে Target Class ম্যানুয়ালি বর্তমান ক্লাসে সেট করে দিন।
                </p>
                <x-filament::button
                    wire:click="mountAction('promote')"
                    icon="heroicon-o-check"
                    color="success">
                    Promote Students
                </x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
