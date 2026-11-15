@php
    $cellClass = 'border border-gray-200 px-2 py-1.5 text-center sm:px-3 sm:py-2 dark:border-white/10';
    $labelCellClass = 'border border-gray-200 px-2 py-1.5 text-left sm:px-3 sm:py-2 dark:border-white/10';
    $blocks = [
        ['title' => 'GPA Summary', 'labelHeading' => 'Result', 'rows' => $gradeRows, 'summaryRows' => [$totalRow, $absentRow, $examineeRow, $passedRow, $failedRow]],
        ['title' => 'Failed Students by Number of Subjects', 'labelHeading' => 'Result', 'rows' => $failRows, 'summaryRows' => [$failedRow]],
        ['title' => 'Subject-wise Failed Students', 'labelHeading' => 'Subject', 'rows' => $subjectRows, 'summaryRows' => [['label' => 'Total Failed Students'] + $failedRow]],
    ];
    $showSectionRank = $breakdownLabel === 'Section';

    // Each tab matches a page of the PDF: the summary, one passed list per group / section, and all failed students.
    $tabs = [['key' => 'summary', 'label' => 'Summary']];

    foreach ($passedLists as $index => $passedList) {
        $tabs[] = [
            'key' => "passed-{$index}",
            'label' => 'Passed' . ($passedList['label'] ? " — {$passedList['label']}" : '') . ' (' . count($passedList['students']) . ')',
            'studentBlocks' => [[
                'title' => 'Passed Students' . ($passedList['label'] ? " — {$breakdownLabel}: {$passedList['label']}" : ''),
                'students' => $passedList['students'],
                'isFailedList' => false,
            ]],
        ];
    }

    $tabs[] = [
        'key' => 'failed',
        'label' => 'Failed (' . $failedRow['total'] . ')',
        'studentBlocks' => $failedLists === []
            ? [['title' => 'Failed Students', 'students' => [], 'isFailedList' => true]]
            : collect($failedLists)->map(fn (array $failedList): array => [
                'title' => $failedList['label'] . ' (' . count($failedList['students']) . ')',
                'students' => $failedList['students'],
                'isFailedList' => true,
            ])->all(),
    ];
@endphp

<div class="space-y-4 text-xs sm:text-sm">
    @if ($isEmpty)
        <div class="rounded-lg border border-dashed border-gray-300 p-4 text-center text-gray-500 dark:border-white/10 dark:text-gray-400">
            <p class="font-medium">এখনো কোনো ranking calculate করা হয়নি</p>
            <p class="mt-1">আগে Exam-এর "Calculate Rankings" বাটন থেকে ranking calculate করুন।</p>
        </div>
    @else
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-6">
            @foreach ([
                ['label' => 'Total Students', 'value' => $totalRow['total'], 'color' => 'text-gray-900 dark:text-white'],
                ['label' => 'Absent (All Subjects)', 'value' => $absentRow['total'], 'color' => 'text-warning-600 dark:text-warning-400'],
                ['label' => 'Examinees', 'value' => $examineeRow['total'], 'color' => 'text-gray-900 dark:text-white'],
                ['label' => 'Passed', 'value' => $passedRow['total'], 'color' => 'text-success-600 dark:text-success-400'],
                ['label' => 'Failed', 'value' => $failedRow['total'], 'color' => 'text-danger-600 dark:text-danger-400'],
                ['label' => 'Pass Rate', 'value' => number_format($passRate, 2).'%', 'color' => 'text-primary-600 dark:text-primary-400'],
            ] as $stat)
                <div class="rounded-lg bg-gray-50 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                    <div class="text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</div>
                    <div class="mt-1 text-lg font-semibold sm:text-2xl {{ $stat['color'] }}">{{ $stat['value'] }}</div>
                </div>
            @endforeach
        </div>

        <div x-data="{ activeTab: 'summary' }" class="space-y-4">
            <div class="flex flex-wrap gap-1.5 sm:gap-2">
                @foreach ($tabs as $tab)
                    <button
                        type="button"
                        x-on:click="activeTab = @js($tab['key'])"
                        x-bind:class="activeTab === @js($tab['key'])
                            ? 'bg-primary-600 text-white dark:bg-primary-500'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10'"
                        class="rounded-lg px-2.5 py-1.5 font-medium transition sm:px-3"
                    >
                        {{ $tab['label'] }}
                    </button>
                @endforeach
            </div>

            <div x-show="activeTab === 'summary'" class="space-y-4">
                @foreach ($blocks as $block)
                    <div>
                        <h3 class="mb-2 font-semibold text-gray-900 dark:text-white">{{ $block['title'] }}</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-gray-700 dark:text-gray-300">
                                <thead class="bg-gray-50 dark:bg-white/5">
                                    <tr>
                                        <th class="{{ $labelCellClass }}">{{ $block['labelHeading'] }}</th>
                                        @foreach ($columns as $column)
                                            <th class="{{ $cellClass }}">{{ $column['label'] }}</th>
                                        @endforeach
                                        <th class="{{ $cellClass }}">{{ count($columns) ? 'Total' : 'Students' }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($block['rows'] as $row)
                                        <tr>
                                            <td class="{{ $labelCellClass }}">{{ $row['label'] }}</td>
                                            @foreach ($columns as $column)
                                                <td class="{{ $cellClass }}">{{ $row['by'][$column['key']] ?? 0 }}</td>
                                            @endforeach
                                            <td class="{{ $cellClass }} font-medium">{{ $row['total'] }}</td>
                                        </tr>
                                    @endforeach
                                    @foreach ($block['summaryRows'] as $row)
                                        <tr class="bg-gray-50 font-semibold dark:bg-white/5">
                                            <td class="{{ $labelCellClass }}">{{ $row['label'] }}</td>
                                            @foreach ($columns as $column)
                                                <td class="{{ $cellClass }}">{{ $row['by'][$column['key']] ?? 0 }}</td>
                                            @endforeach
                                            <td class="{{ $cellClass }}">{{ $row['total'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>

            @foreach ($tabs as $tab)
                @continue(! isset($tab['studentBlocks']))

                <div x-show="activeTab === @js($tab['key'])" x-cloak class="space-y-4">
                    @foreach ($tab['studentBlocks'] as $studentBlock)
                        <div>
                            <h3 class="mb-2 font-semibold text-gray-900 dark:text-white">{{ $studentBlock['title'] }}</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full border-collapse text-gray-700 dark:text-gray-300">
                                    <thead class="bg-gray-50 dark:bg-white/5">
                                        <tr>
                                            <th class="{{ $cellClass }}">SL</th>
                                            <th class="{{ $cellClass }}">Roll</th>
                                            <th class="{{ $labelCellClass }}">Name</th>
                                            @if ($studentBlock['isFailedList'] && $breakdownLabel)
                                                <th class="{{ $cellClass }}">{{ $breakdownLabel }}</th>
                                            @endif
                                            <th class="{{ $cellClass }}">GPA</th>
                                            <th class="{{ $cellClass }}">Class Rank</th>
                                            @if ($showSectionRank)
                                                <th class="{{ $cellClass }}">Section Rank</th>
                                            @endif
                                            @if ($studentBlock['isFailedList'])
                                                <th class="{{ $labelCellClass }}">Failed Subjects</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($studentBlock['students'] as $student)
                                            <tr>
                                                <td class="{{ $cellClass }}">{{ $loop->iteration }}</td>
                                                <td class="{{ $cellClass }}">{{ $student['roll'] ?? '—' }}</td>
                                                <td class="{{ $labelCellClass }} whitespace-nowrap">{{ $student['name'] }}</td>
                                                @if ($studentBlock['isFailedList'] && $breakdownLabel)
                                                    <td class="{{ $cellClass }}">{{ $student['breakdown'] ?? '—' }}</td>
                                                @endif
                                                <td class="{{ $cellClass }} font-medium">{{ number_format($student['gpa'], 2) }}</td>
                                                <td class="{{ $cellClass }}">{{ $student['class_rank'] ?? '—' }}</td>
                                                @if ($showSectionRank)
                                                    <td class="{{ $cellClass }}">{{ $student['section_rank'] ?? '—' }}</td>
                                                @endif
                                                @if ($studentBlock['isFailedList'])
                                                    <td class="{{ $labelCellClass }} text-danger-600 dark:text-danger-400">{{ $student['failed_subjects'] }}</td>
                                                @endif
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="{{ $cellClass }} text-gray-500 dark:text-gray-400">কোনো student নেই</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif
</div>
