<div class="space-y-3">
    @if ($rows->isEmpty())
        <p class="py-4 text-center text-sm text-gray-500">No marks recorded for this student.</p>
    @else

        {{-- Marks Table --}}
        <div class="overflow-x-auto rounded-lg border border-gray-100 dark:border-gray-700">
            <table class="w-full text-[10px] sm:text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                        <th class="px-2 py-2 text-left font-semibold text-gray-700 dark:text-gray-300 sm:px-3 sm:py-2.5">Subject</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-700 dark:text-gray-300 sm:px-3 sm:py-2.5">Marks</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-700 dark:text-gray-300 sm:px-3 sm:py-2.5">Grade</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-700 dark:text-gray-300 sm:px-3 sm:py-2.5">Best</th>
                        <th class="px-2 py-2 text-left font-semibold text-gray-700 dark:text-gray-300 sm:px-3 sm:py-2.5">Best Student</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($rows as $row)
                        <tr class="{{ $row['is_top_scorer'] ? 'bg-yellow-50 dark:bg-yellow-900/10' : 'hover:bg-gray-50 dark:hover:bg-gray-900/30' }}">

                            {{-- Subject --}}
                            <td class="px-2 py-1.5 text-left font-medium text-gray-800 dark:text-gray-200 sm:px-3 sm:py-2">
                                <span
                                    x-data="{ expanded: false }"
                                    @click="expanded = ! expanded"
                                    class="block cursor-pointer select-none"
                                    :class="expanded ? 'whitespace-normal break-words' : 'max-w-[80px] truncate sm:max-w-[140px] md:max-w-none'"
                                >
                                    {{ $row['subject_name'] }}
                                </span>
                            </td>

                            {{-- Marks --}}
                            <td class="px-2 py-1.5 text-center sm:px-3 sm:py-2">
                                @if ($row['is_absent'])
                                    <span class="inline-flex items-center rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400 sm:text-xs">
                                        Absent
                                    </span>
                                @else
                                    <span class="{{ $row['is_top_scorer'] ? 'font-bold text-yellow-700 dark:text-yellow-400' : 'text-gray-700 dark:text-gray-300' }}">
                                        {{ $row['total_marks'] }}
                                    </span>
                                @endif
                            </td>

                            {{-- Grade --}}
                            <td class="px-2 py-1.5 text-center sm:px-3 sm:py-2">
                                @if ($row['grade_label'])
                                    @php $gc = $row['grade_color']; @endphp
                                    <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium sm:text-xs sm:px-2
                                        {{ $gc === 'success' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                        {{ $gc === 'info'    ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : '' }}
                                        {{ $gc === 'warning' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' : '' }}
                                        {{ $gc === 'danger'  ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : '' }}">
                                        {{ $row['grade_label'] }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>

                            {{-- Best Marks --}}
                            <td class="px-2 py-1.5 text-center font-semibold text-gray-800 dark:text-gray-200 sm:px-3 sm:py-2">
                                {{ $row['best_marks'] ?? '—' }}
                            </td>

                            {{-- Best Student --}}
                            <td class="px-2 py-1.5 text-left text-gray-600 dark:text-gray-400 sm:px-3 sm:py-2">
                                @if ($row['best_students'])
                                    <span class="block max-w-[120px] truncate md:max-w-none">
                                        {{ $row['best_students'] }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Summary Footer --}}
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-100 bg-gray-50 px-3 py-3 dark:border-gray-700 dark:bg-gray-800/50 sm:px-4">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-gray-600 dark:text-gray-400 sm:text-xs">
                <span>Total: <strong class="text-gray-800 dark:text-gray-200">{{ $summary['total_marks'] }}</strong></span>
                <span class="text-gray-300 dark:text-gray-600">|</span>
                <span>Class Rank: <strong class="text-gray-800 dark:text-gray-200">#{{ $summary['class_rank'] }}</strong></span>
                @if ($summary['section_rank'])
                    <span class="text-gray-300 dark:text-gray-600">|</span>
                    <span>Section Rank: <strong class="text-gray-800 dark:text-gray-200">#{{ $summary['section_rank'] }}</strong></span>
                @endif
            </div>
            @php $oc = $summary['overall_grade_color']; @endphp
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-semibold sm:text-xs sm:px-3
                {{ $oc === 'success' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : '' }}
                {{ $oc === 'info'    ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : '' }}
                {{ $oc === 'warning' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' : '' }}
                {{ $oc === 'danger'  ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : '' }}">
                GPA {{ $summary['gpa'] }} — {{ $summary['overall_grade_label'] }}
            </span>
        </div>

    @endif
</div>
