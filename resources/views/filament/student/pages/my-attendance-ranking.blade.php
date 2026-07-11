<x-filament-panels::page>
    @php
        $students = $this->getStudents();
        $myStudentId = $this->getMyStudentId();
    @endphp

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        @if ($students->isEmpty())
            <x-filament::empty-state icon="heroicon-o-users">
                <x-slot name="heading">No students found in your class.</x-slot>
            </x-filament::empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-[11px] font-semibold tracking-wide text-gray-500 uppercase sm:text-xs dark:border-gray-700 dark:text-gray-400">
                            <th scope="col" class="w-10 px-2 py-2 sm:w-12 sm:px-4 sm:py-2.5">#</th>
                            <th scope="col" class="px-2 py-2 sm:px-4 sm:py-2.5">Name</th>
                            <th scope="col" class="w-16 px-2 py-2 text-center sm:w-24 sm:px-4 sm:py-2.5">Roll</th>
                            <th scope="col" class="w-16 px-2 py-2 text-right sm:w-24 sm:px-4 sm:py-2.5">Present</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($students as $index => $student)
                            <tr class="{{ $student['id'] === $myStudentId ? 'bg-primary-50 dark:bg-primary-950' : '' }}">
                                <td class="px-2 py-2 sm:px-4 sm:py-2.5">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-warning-100 text-[10px] font-bold text-warning-700 sm:h-6 sm:w-6 sm:text-xs dark:bg-warning-900 dark:text-warning-300">
                                        {{ $index + 1 }}
                                    </span>
                                </td>
                                <td class="max-w-0 truncate px-2 py-2 font-medium text-gray-900 sm:px-4 sm:py-2.5 dark:text-white">
                                    {{ $student['name'] }}
                                    @if ($student['id'] === $myStudentId)
                                        <span class="font-normal text-primary-600 dark:text-primary-400">(You)</span>
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-center text-gray-500 sm:px-4 sm:py-2.5 dark:text-gray-400">{{ $student['roll_no'] ?? '—' }}</td>
                                <td class="px-2 py-2 text-right font-semibold text-success-600 sm:px-4 sm:py-2.5 dark:text-success-400">{{ $student['present_count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
