@php
    $cellClass = 'border border-gray-200 px-2 py-1.5 text-center sm:px-3 sm:py-2 dark:border-white/10';
    $labelCellClass = 'border border-gray-200 px-2 py-1.5 text-left sm:px-3 sm:py-2 dark:border-white/10';
    $blocks = [
        ['title' => 'GPA Summary', 'labelHeading' => 'Result', 'rows' => $gradeRows, 'summaryRows' => [$totalRow, $passedRow, $failedRow]],
        ['title' => 'Failed Students by Number of Subjects', 'labelHeading' => 'Result', 'rows' => $failRows, 'summaryRows' => [$failedRow]],
        ['title' => 'Subject-wise Failed Students', 'labelHeading' => 'Subject', 'rows' => $subjectRows, 'summaryRows' => [['label' => 'Total Failed Students'] + $failedRow]],
    ];
@endphp

<div class="space-y-4 text-xs sm:text-sm">
    @if ($isEmpty)
        <div class="rounded-lg border border-dashed border-gray-300 p-4 text-center text-gray-500 dark:border-white/10 dark:text-gray-400">
            <p class="font-medium">এখনো কোনো ranking calculate করা হয়নি</p>
            <p class="mt-1">আগে Exam-এর "Calculate Rankings" বাটন থেকে ranking calculate করুন।</p>
        </div>
    @else
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
            @foreach ([
                ['label' => 'Total Students', 'value' => $totalRow['total'], 'color' => 'text-gray-900 dark:text-white'],
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
    @endif
</div>
