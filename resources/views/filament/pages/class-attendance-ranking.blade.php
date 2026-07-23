<x-filament-panels::page>
    @php $students = $this->getStudents(); @endphp

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        @if ($students->isEmpty())
            <x-filament::empty-state icon="heroicon-o-users">
                <x-slot name="heading">No students found in this class.</x-slot>
            </x-filament::empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <th scope="col" class="px-2 py-2">#</th>
                            <th scope="col" class="px-2 py-2">Name</th>
                            <th scope="col" class="px-2 py-2">Roll</th>
                            <th scope="col" class="px-2 py-2 text-right">Present</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($students as $index => $student)
                            <tr>
                                <td class="px-2 py-2">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-warning-100 text-xs font-bold text-warning-700 dark:bg-warning-900 dark:text-warning-300">
                                        {{ $index + 1 }}
                                    </span>
                                </td>
                                <td class="px-2 py-2 max-w-[10rem] break-words font-medium text-gray-900 sm:max-w-none dark:text-white">{{ $student['name'] }}</td>
                                <td class="px-2 py-2 text-center text-gray-500 sm:px-4 dark:text-gray-400">{{ $student['roll_no'] ?? '—' }}</td>
                                <td class="px-2 py-2 text-right font-semibold text-success-600 sm:px-4 dark:text-success-400">{{ $student['present_count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
