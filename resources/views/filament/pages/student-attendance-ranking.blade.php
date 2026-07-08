<x-filament-panels::page>
    <div class="space-y-5">

        {{-- Top 5 School-wide --}}
        @php $topStudents = $this->getTopStudents(5); @endphp
        <div>
            <h2 class="mb-2 flex items-center gap-x-1.5 text-sm font-semibold text-gray-900 dark:text-white">
                <x-heroicon-o-trophy class="h-4 w-4 text-primary-500" />
                Top 5 Students (School-wide)
            </h2>

            @if ($topStudents->isEmpty())
                <x-filament::empty-state icon="heroicon-o-trophy">
                    <x-slot name="heading">No attendance records found.</x-slot>
                </x-filament::empty-state>
            @else
                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                    @foreach ($topStudents as $student)
                        <div class="flex flex-col items-center gap-y-1.5 rounded-lg bg-white p-3 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-warning-100 text-xs font-bold text-warning-700 dark:bg-warning-900 dark:text-warning-300">
                                {{ $student['roll_no'] ?? '—' }}
                            </span>
                            <p class="w-full truncate text-sm font-medium text-gray-900 dark:text-white">{{ $student['name'] }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $student['class_name'] ?? '—' }}</p>
                            <p class="text-lg font-bold text-success-600 dark:text-success-400">{{ $student['present_count'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">days present</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Class-wise --}}
        @php $classWise = $this->getClassWiseTopStudents(); @endphp
        <div class="mt-3 sm:mt-4">
            <h2 class="mb-2 flex items-center gap-x-1.5 text-sm font-semibold text-gray-900 dark:text-white">
                <x-heroicon-o-academic-cap class="h-4 w-4 text-primary-500" />
                Class-wise Top Attendance
            </h2>

            @if ($classWise->isEmpty())
                <x-filament::empty-state icon="heroicon-o-academic-cap">
                    <x-slot name="heading">No active classes found.</x-slot>
                </x-filament::empty-state>
            @else
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($classWise as $entry)
                        <a
                            href="{{ \App\Filament\Pages\ClassAttendanceRanking::getUrl(['classId' => $entry['class']->id]) }}"
                            class="block rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 transition-shadow duration-150 hover:shadow-md dark:bg-gray-900 dark:ring-white/10"
                        >
                            <div class="mb-2 flex items-center gap-x-2">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary-50 dark:bg-primary-950">
                                    <x-heroicon-o-academic-cap class="h-4 w-4 text-primary-600 dark:text-primary-400" />
                                </div>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $entry['class']->name }}</span>
                            </div>

                            @if ($entry['top']->isEmpty())
                                <p class="text-xs text-gray-400 dark:text-gray-500">No attendance recorded yet.</p>
                            @else
                                <div class="space-y-1.5">
                                    @foreach ($entry['top'] as $student)
                                        <div class="flex items-center justify-between gap-x-2 text-xs sm:text-sm">
                                            <div class="flex min-w-0 items-center gap-x-1.5">
                                                <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-warning-100 text-[10px] font-bold text-warning-700 dark:bg-warning-900 dark:text-warning-300">{{ $student['roll_no'] ?? '—' }}</span>
                                                <span class="truncate text-gray-700 dark:text-gray-300">{{ $student['name'] }}</span>
                                            </div>
                                            <span class="shrink-0 font-semibold text-success-600 dark:text-success-400">{{ $student['present_count'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
