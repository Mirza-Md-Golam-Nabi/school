<x-filament-panels::page>
    @php
        $attendance = $this->getAttendanceSummary();
        $fee = $this->getFeeSummary();
        $exam = $this->getExamResultSummary();
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3" style="gap: 1rem;">
        {{-- Attendance — informational only, not clickable --}}
        <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="mb-2 flex items-center gap-x-2">
                <x-heroicon-o-calendar-days class="h-5 w-5 text-gray-400" />
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Attendance</h3>
            </div>
            <div class="flex items-start justify-between gap-x-4">
                <div>
                    <p class="text-2xl font-bold text-gray-950 dark:text-white">
                        {{ $attendance['presentDays'] }} / {{ $attendance['workingDays'] }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Days Present</p>
                </div>

                {{-- ডান পাশ --}}
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Rank {{ $attendance['rank'] ?? '-' }} of {{ $attendance['totalStudents'] }}
                </p>
            </div>
        </div>

        {{-- School Fee — clickable --}}
        <a href="{{ $fee['url'] }}"
            class="group rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-950/5 transition-all duration-150 hover:shadow-md dark:bg-gray-900 dark:ring-white/10">
            <div class="mb-2 flex items-center gap-x-2">
                <x-heroicon-o-banknotes class="h-5 w-5 text-gray-400" />
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">School Fee</h3>
            </div>
            <p class="text-2xl font-bold text-gray-950 dark:text-white">
                ৳{{ number_format($fee['totalPaid'], 2) }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Total Paid</p>
        </a>

        {{-- Exam Result — clickable --}}
        @if ($exam)
            <a href="{{ $exam['url'] }}"
                class="group rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-950/5 transition-all duration-150 hover:shadow-md dark:bg-gray-900 dark:ring-white/10">
                <div class="mb-2 flex items-center gap-x-2">
                    <x-heroicon-o-trophy class="h-5 w-5 text-gray-400" />
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Exam Result</h3>
                </div>
                <p class="text-2xl font-bold text-gray-950 dark:text-white">
                    Rank {{ $exam['classRank'] ?? '-' }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    GPA {{ $exam['gpa'] }} ({{ $exam['gradeLabel'] }})
                </p>
            </a>
        @else
            <div
                class="rounded-lg bg-white p-4 opacity-60 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="mb-2 flex items-center gap-x-2">
                    <x-heroicon-o-trophy class="h-5 w-5 text-gray-400" />
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Exam Result</h3>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">No result available</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
