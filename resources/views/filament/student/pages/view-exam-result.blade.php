<x-filament-panels::page>
    @php
        use App\Enums\Grade;
        $rankings = $this->getRankings();
        $myStudentId = $this->getMyStudentId();
        $hasSections = $rankings->filter(fn($r) => $r->section !== null)->isNotEmpty();
    @endphp

    <div class="space-y-3 sm:space-y-4">

        {{-- Exam Info --}}
        <div
            class="rounded-lg border border-gray-200 bg-white px-4 py-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:px-6 sm:py-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <h2 class="text-base font-bold text-gray-800 dark:text-gray-100 sm:text-xl">
                        {{ $record->examType?->name ?? 'Exam Results' }}
                    </h2>
                    <div
                        class="mt-1 flex flex-col gap-0.5 text-xs text-gray-500 dark:text-gray-400 sm:flex-row sm:flex-wrap sm:gap-x-4 sm:gap-y-0.5">
                        <span>
                            <span class="font-medium text-gray-700 dark:text-gray-300">Class:</span>
                            {{ $record->class?->name }}
                        </span>
                        <span class="hidden sm:inline">
                            <span class="font-medium text-gray-700 dark:text-gray-300">Session:</span>
                            {{ $record->session_year }}
                        </span>
                        @if ($record->start_date)
                            <span>
                                <span class="font-medium text-gray-700 dark:text-gray-300">Date:</span>
                                {{ $record->start_date->format('d M Y') }} – {{ $record->end_date?->format('d M Y') }}
                            </span>
                        @endif
                    </div>
                </div>
                <div class="flex-shrink-0 text-right">
                    <p class="text-xl font-bold text-gray-800 dark:text-gray-100 sm:text-2xl">{{ $rankings->count() }}
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">Students</p>
                </div>
            </div>
        </div>

        {{-- Rankings Table --}}
        @if ($rankings->isEmpty())
            <div
                class="rounded-xl border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                Results have not been published yet.
            </div>
        @else
            <div
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto">
                    <table class="w-full text-[10px] sm:text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                                <th
                                    class="px-2 py-2.5 text-center font-semibold text-gray-700 dark:text-gray-300 sm:px-4 sm:py-3">
                                    Rank</th>
                                @if ($hasSections)
                                    <th
                                        class="hidden px-2 py-2.5 text-center font-semibold text-gray-700 dark:text-gray-300 sm:table-cell sm:px-3 sm:py-3">
                                        Sec. Rank</th>
                                @endif
                                <th
                                    class="hidden px-2 py-2.5 text-center font-semibold text-gray-700 dark:text-gray-300 sm:table-cell sm:px-3 sm:py-3">
                                    Roll</th>
                                <th
                                    class="px-2 py-2.5 text-left font-semibold text-gray-700 dark:text-gray-300 sm:px-4 sm:py-3">
                                    Name</th>
                                @if ($hasSections)
                                    <th
                                        class="hidden px-2 py-2.5 text-center font-semibold text-gray-700 dark:text-gray-300 md:table-cell md:px-4 md:py-3">
                                        Section</th>
                                @endif
                                <th
                                    class="px-2 py-2.5 text-center font-semibold text-gray-700 dark:text-gray-300 sm:px-4 sm:py-3">
                                    Marks</th>
                                <th
                                    class="px-2 py-2.5 text-center font-semibold text-gray-700 dark:text-gray-300 sm:px-4 sm:py-3">
                                    GPA</th>
                                <th
                                    class="px-2 py-2.5 text-center font-semibold text-gray-700 dark:text-gray-300 sm:px-4 sm:py-3">
                                    Grade</th>
                                <th class="px-2 py-2.5 sm:px-3 sm:py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($rankings as $ranking)
                                @php
                                    $isMe = (int) $ranking->student_id === (int) $myStudentId;
                                    $grade = Grade::fromGpa((float) $ranking->gpa);
                                    $gradeColor = $grade->getColor();
                                    $rankLabel = match ((int) $ranking->class_rank) {
                                        1 => '🥇 1st',
                                        2 => '🥈 2nd',
                                        3 => '🥉 3rd',
                                        default => (string) $ranking->class_rank,
                                    };
                                    $badgeClass = match ($gradeColor) {
                                        'success'
                                            => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                        'info' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                        'warning'
                                            => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
                                        'danger' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                        default => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                                    };
                                @endphp
                                <tr
                                    class="{{ $isMe
                                        ? 'bg-blue-50 font-semibold dark:bg-blue-900/20'
                                        : 'transition-colors duration-100 hover:bg-gray-50 dark:hover:bg-gray-800/40' }}">

                                    {{-- Rank --}}
                                    <td class="px-2 py-2.5 text-center sm:px-4 sm:py-3">
                                        <span
                                            class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium sm:text-xs sm:px-2.5
                                            {{ (int) $ranking->class_rank === 1 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' : '' }}
                                            {{ (int) $ranking->class_rank === 2 ? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' : '' }}
                                            {{ (int) $ranking->class_rank === 3 ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' : '' }}
                                            {{ (int) $ranking->class_rank > 3 ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-400' : '' }}">
                                            {{ $rankLabel }}
                                        </span>
                                    </td>

                                    {{-- Section Rank --}}
                                    @if ($hasSections)
                                        <td
                                            class="hidden px-2 py-2.5 text-center text-gray-600 dark:text-gray-400 sm:table-cell sm:px-3 sm:py-3">
                                            {{ $ranking->section_rank ?? '—' }}
                                        </td>
                                    @endif

                                    {{-- Roll: hidden on mobile --}}
                                    <td
                                        class="hidden px-2 py-2.5 text-center text-gray-700 dark:text-gray-300 sm:table-cell sm:px-3 sm:py-3">
                                        {{ $ranking->student?->roll_no }}
                                    </td>

                                    {{-- Name --}}
                                    <td class="px-2 py-2.5 text-gray-800 dark:text-gray-200 sm:px-4 sm:py-3">
                                        <span class="block max-w-[110px] truncate sm:max-w-[180px] md:max-w-none">
                                            {{ $ranking->student?->user?->name }}
                                        </span>
                                    </td>

                                    {{-- Section --}}
                                    @if ($hasSections)
                                        <td
                                            class="hidden px-2 py-2.5 text-center text-gray-600 dark:text-gray-400 md:table-cell md:px-4 md:py-3">
                                            {{ $ranking->section?->name ?? '—' }}
                                        </td>
                                    @endif

                                    {{-- Total Marks --}}
                                    <td
                                        class="px-2 py-2.5 text-center font-medium text-gray-800 dark:text-gray-200 sm:px-4 sm:py-3">
                                        {{ $ranking->total_marks }}
                                    </td>

                                    {{-- GPA --}}
                                    <td class="px-2 py-2.5 text-center sm:px-4 sm:py-3">
                                        <span
                                            class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium sm:text-xs sm:px-2 {{ $badgeClass }}">
                                            {{ number_format((float) $ranking->gpa, 2) }}
                                        </span>
                                    </td>

                                    {{-- Grade --}}
                                    <td class="px-2 py-2.5 text-center sm:px-4 sm:py-3">
                                        <span
                                            class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium sm:text-xs sm:px-2 {{ $badgeClass }}">
                                            {{ $grade->getLabel() }}
                                        </span>
                                    </td>

                                    {{-- Eye button --}}
                                    <td class="px-2 py-2.5 text-center sm:px-3 sm:py-3">
                                        @if ($isMe)
                                            <x-filament::modal width="4xl">
                                                <x-slot name="trigger">
                                                    <x-filament::icon-button size="sm" color="warning"
                                                        icon="heroicon-o-eye" />
                                                </x-slot>
                                                <x-slot name="heading">My Subject-wise Marks</x-slot>
                                                {!! $this->getMyMarksDetail($ranking->id) !!}
                                            </x-filament::modal>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
