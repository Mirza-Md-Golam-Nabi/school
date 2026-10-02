<x-filament-panels::page>
    @if ($classes->isEmpty())
        <x-filament::empty-state
            icon="heroicon-o-identification"
            heading="No active classes found"
            description="Add active classes first to generate student ID cards."
        />
    @else
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @foreach ($classes as $class)
                @php
                    $accent = \App\Support\ClassAccentColor::for($class->id);
                    $studentCount = $class->active_students_count;
                    $hasStudents = $studentCount > 0;
                @endphp

                <div
                    @class([
                        'flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10',
                        'transition-shadow duration-200 hover:shadow-lg' => $hasStudents,
                        'opacity-60' => ! $hasStudents,
                    ])
                >
                    {{-- Accent bar --}}
                    <div class="h-1.5" style="background-color:{{ $accent }}"></div>

                    {{-- Header --}}
                    <div class="flex items-center gap-2 px-3 pt-3 pb-2">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                             style="background-color:{{ $accent }}22">
                            <x-heroicon-o-identification class="h-4 w-4" style="color:{{ $accent }}" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-gray-900 dark:text-white">
                                {{ $class->name }}
                            </p>
                            <x-filament::badge
                                size="sm"
                                :color="$studentCount > 0 ? 'success' : 'gray'"
                            >
                                {{ $studentCount }} {{ Str::plural('student', $studentCount) }}
                            </x-filament::badge>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="mt-auto border-t border-gray-100 px-3 py-2 dark:border-gray-800">
                        @if ($hasStudents)
                            <div class="grid grid-cols-2 gap-2">
                                <x-filament::button
                                    tag="a"
                                    :href="route('student-id-cards.class.view', ['class' => $class->id])"
                                    target="_blank"
                                    icon="heroicon-o-eye"
                                    size="xs"
                                    color="gray"
                                >
                                    View
                                </x-filament::button>

                                <x-filament::button
                                    tag="a"
                                    :href="route('student-id-cards.class.download', ['class' => $class->id])"
                                    icon="heroicon-o-arrow-down-tray"
                                    size="xs"
                                >
                                    Download
                                </x-filament::button>
                            </div>
                        @else
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">No students yet</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
