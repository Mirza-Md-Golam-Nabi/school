<x-filament-panels::page>
    @php
        $classes = $this->getClasses();
        $exams = $this->getExams();
        $exam = $this->resolveExam();
        $students = $this->getStudents();
        $deviceTotals = $this->getDeviceTotals();
        $savedIds = $this->getSavedStudentIds()->flip();
        $inputClass = 'rounded-lg border border-gray-400 bg-white px-2 py-1.5 text-center text-xs shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 sm:text-sm dark:border-gray-500 dark:bg-gray-800 dark:text-white';
        $selectClass = 'w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 sm:text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white';
    @endphp

    <div class="space-y-3">

        {{-- Class / exam picker --}}
        <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Class</span>
                    <select wire:model.live="classId" class="{{ $selectClass }}">
                        @if ($classes->isEmpty())
                            <option value="0">—</option>
                        @endif
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}">{{ $class->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Exam</span>
                    <select wire:model.live="examId" class="{{ $selectClass }}">
                        @if ($exams->isEmpty())
                            <option value="0">—</option>
                        @endif
                        @foreach ($exams as $examOption)
                            <option value="{{ $examOption->id }}">{{ $examOption->examType?->name }} ({{ $examOption->session_year }})</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">মোট কার্যদিবস</span>
                    <input
                        type="number"
                        min="1"
                        max="366"
                        step="1"
                        wire:model.blur="workingDays"
                        @disabled(! $exam)
                        class="{{ $inputClass }} w-full @error('workingDays') border-danger-500 dark:border-danger-500 @enderror"
                    >
                    @error('workingDays')
                        <p class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                </label>
            </div>

            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                এই পরীক্ষা পর্যন্ত (খাতা-কলম + ডিভাইস মিলিয়ে) প্রত্যেক ছাত্রের মোট উপস্থিতি লিখুন। সেভ করলে মার্কশিট আর attendance ranking-এ এই সংখ্যাই দেখাবে।
                ঘর খালি রাখলে সেই ছাত্রের জন্য ডিভাইসের হিসাব চলবে।
                @if ($exam)
                    ডিভাইসে নেওয়া কার্যদিবস: <span class="font-semibold">{{ $deviceTotals['working_days'] }}</span>
                @endif
            </p>
        </div>

        @if (! $exam)
            <x-filament::empty-state icon="heroicon-o-clipboard-document-list">
                <x-slot name="heading">এই ক্লাসে এখনো কোনো পরীক্ষা নেই।</x-slot>
            </x-filament::empty-state>
        @elseif ($students->isEmpty())
            <x-filament::empty-state icon="heroicon-o-users">
                <x-slot name="heading">এই ক্লাসে কোনো active student নেই।</x-slot>
            </x-filament::empty-state>
        @else
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center gap-x-1.5 border-b border-gray-200 px-3 py-2.5 dark:border-gray-700 sm:px-4">
                    <x-heroicon-o-users class="h-4 w-4 text-gray-400" />
                    <span class="text-xs font-semibold text-gray-900 dark:text-white sm:text-sm">
                        Students ({{ $students->count() }})
                    </span>
                    @if ($savedIds->isNotEmpty())
                        <x-filament::badge color="success" size="sm" class="ml-auto">
                            সেভ করা: {{ $savedIds->count() }}
                        </x-filament::badge>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs sm:text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50">
                                <th class="w-12 px-2 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-4">Roll</th>
                                <th class="px-2 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-4">Student</th>
                                <th class="px-2 py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-3">Device</th>
                                <th class="px-2 py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-3">
                                    উপস্থিত
                                    @if (filled($workingDays))
                                        <span class="block font-normal normal-case text-gray-400">/ {{ $workingDays }}</span>
                                    @endif
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($students as $student)
                                <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/30" wire:key="student-{{ $student->id }}">
                                    <td class="px-2 py-2 text-xs text-gray-500 dark:text-gray-400 sm:px-4">{{ $student->roll_no }}</td>
                                    <td class="px-2 py-2 font-medium text-gray-900 dark:text-white sm:px-4">
                                        {{ $student->user?->name ?? '—' }}
                                        @if ($savedIds->has($student->id))
                                            <x-heroicon-s-check-circle class="inline h-3.5 w-3.5 text-success-500" title="সেভ করা" />
                                        @endif
                                    </td>
                                    <td class="px-2 py-2 text-center text-gray-500 dark:text-gray-400 sm:px-3">
                                        {{ $deviceTotals['present_days']->get($student->id, 0) }}
                                    </td>
                                    <td class="px-2 py-1.5 text-center sm:px-3">
                                        <input
                                            type="number"
                                            min="0"
                                            step="1"
                                            wire:model.blur="presentDays.{{ $student->id }}"
                                            class="{{ $inputClass }} w-16 sm:w-20 @error('presentDays.'.$student->id) border-danger-500 dark:border-danger-500 @enderror"
                                        >
                                        @error('presentDays.'.$student->id)
                                            <p class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                        @enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex flex-col gap-2 rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    {{ $this->resetToDeviceCountAction }}
                </div>

                <x-filament::button
                    wire:click="save"
                    wire:loading.attr="disabled"
                    icon="heroicon-o-check"
                >
                    <span wire:loading.remove wire:target="save">Save Totals</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
