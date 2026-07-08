<x-filament-panels::page>
    @php $students = $this->getStudents(); @endphp

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        @if ($students->isEmpty())
            <x-filament::empty-state icon="heroicon-o-users">
                <x-slot name="heading">No students found in this class.</x-slot>
            </x-filament::empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <th scope="col" class="w-12 px-4 py-2.5">#</th>
                            <th scope="col" class="px-4 py-2.5">Name</th>
                            <th scope="col" class="w-24 px-4 py-2.5 text-center">Roll</th>
                            <th scope="col" class="w-24 px-4 py-2.5 text-right">Present</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($students as $index => $student)
                            <tr>
                                <td class="px-4 py-2.5">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-warning-100 text-xs font-bold text-warning-700 dark:bg-warning-900 dark:text-warning-300">
                                        {{ $index + 1 }}
                                    </span>
                                </td>
                                <td class="max-w-0 truncate px-4 py-2.5 font-medium text-gray-900 dark:text-white">{{ $student['name'] }}</td>
                                <td class="px-4 py-2.5 text-center text-gray-500 dark:text-gray-400">{{ $student['roll_no'] ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-right font-semibold text-success-600 dark:text-success-400">{{ $student['present_count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
