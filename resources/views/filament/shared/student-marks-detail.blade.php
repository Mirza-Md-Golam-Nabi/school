<div class="space-y-2">
    @if ($myResults->isEmpty())
        <p class="text-center text-sm text-gray-500 py-4">No marks recorded for this student.</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-300">Subject</th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 dark:text-gray-300">Marks</th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 dark:text-gray-300">Grade</th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 dark:text-gray-300">Best Marks</th>
                    <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-300">Best Student</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($myResults as $subjectId => $result)
                    @php
                        $stats = $subjectStats[$subjectId] ?? null;
                        $config = $subjectConfigs[$subjectId] ?? null;
                        $fullMarks = $config?->total_marks ?: 100;
                        $percentage = (! $result->is_absent && $fullMarks > 0)
                            ? ($result->total_marks / $fullMarks) * 100
                            : 0;
                        $grade = (! $result->is_absent && $result->total_marks > 0)
                            ? \App\Enums\Grade::fromMarks($percentage)
                            : null;
                        $isTopScorer = $stats
                            && ! $result->is_absent
                            && (float) $result->total_marks === (float) $stats['best_marks'];
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/30 {{ $isTopScorer ? 'bg-yellow-50 dark:bg-yellow-900/10' : '' }}">
                        <td class="px-3 py-2 font-medium text-gray-800 dark:text-gray-200">
                            {{ $result->subject?->name ?? '—' }}
                        </td>
                        <td class="px-3 py-2 text-center">
                            @if ($result->is_absent)
                                <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                    Absent
                                </span>
                            @else
                                <span class="{{ $isTopScorer ? 'font-bold text-yellow-700 dark:text-yellow-400' : 'text-gray-700 dark:text-gray-300' }}">
                                    {{ $result->total_marks }}
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-center">
                            @if ($grade)
                                @php $color = $grade->getColor(); @endphp
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $color === 'success' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                    {{ $color === 'info' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : '' }}
                                    {{ $color === 'warning' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' : '' }}
                                    {{ $color === 'danger' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : '' }}">
                                    {{ $grade->getLabel() }}
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-center font-semibold text-gray-800 dark:text-gray-200">
                            {{ $stats ? $stats['best_marks'] : '—' }}
                        </td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">
                            @if ($stats && $stats['best_students']->isNotEmpty())
                                {{ $stats['best_students']->implode(', ') }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50">
                    <td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-300">Total</td>
                    <td class="px-3 py-2 text-center font-bold text-gray-800 dark:text-gray-200">
                        {{ $ranking->total_marks }}
                    </td>
                    <td class="px-3 py-2 text-center">
                        @php $overallGrade = \App\Enums\Grade::fromGpa((float) $ranking->gpa); $c = $overallGrade->getColor(); @endphp
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                            {{ $c === 'success' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : '' }}
                            {{ $c === 'info' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : '' }}
                            {{ $c === 'warning' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' : '' }}
                            {{ $c === 'danger' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : '' }}">
                            GPA {{ number_format((float) $ranking->gpa, 2) }} — {{ $overallGrade->getLabel() }}
                        </span>
                    </td>
                    <td colspan="2" class="px-3 py-2 text-center text-gray-500 dark:text-gray-400 text-xs">
                        Class Rank: <span class="font-semibold">{{ $ranking->class_rank }}</span>
                        @if ($ranking->section_rank)
                            &nbsp;|&nbsp; Section Rank: <span class="font-semibold">{{ $ranking->section_rank }}</span>
                        @endif
                    </td>
                </tr>
            </tfoot>
        </table>
    @endif
</div>
