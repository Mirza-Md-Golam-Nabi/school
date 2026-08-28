<x-filament-panels::page>
    @if ($classes->isEmpty())
        <x-filament::empty-state
            icon="heroicon-o-table-cells"
            heading="No active classes found"
            description="Add active classes first to generate seat plans."
        />
    @else
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @foreach ($classes as $class)
                @php
                    $accent = \App\Support\ClassAccentColor::for($class->id);
                    $studentCount = $class->active_students_count;
                    $url = $studentCount > 0 ? route('seat-plan.class.download', ['class' => $class->id]) : null;
                @endphp

                <a
                    href="{{ $url ?? '#' }}"
                    @if (! $url) onclick="return false;" aria-disabled="true" @endif
                    @class([
                        'group flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10',
                        'transition-all duration-200 hover:-translate-y-1 hover:shadow-lg' => $url,
                        'cursor-not-allowed opacity-60' => ! $url,
                    ])
                >
                    {{-- Accent bar --}}
                    <div class="h-1.5" style="background-color:{{ $accent }}"></div>

                    {{-- Header --}}
                    <div class="flex items-center gap-2 px-3 pt-3 pb-2">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                             style="background-color:{{ $accent }}22">
                            <x-heroicon-o-table-cells class="h-4 w-4" style="color:{{ $accent }}" />
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
                    <div class="flex items-center justify-between border-t border-gray-100 px-3 py-2 dark:border-gray-800">
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">
                            {{ $url ? 'Download PDF' : 'No students yet' }}
                        </p>

                        @if ($url)
                            <x-filament::icon-button
                                icon="heroicon-o-arrow-down-tray"
                                size="sm"
                                color="gray"
                                tag="span"
                                class="transition-transform duration-150 group-hover:translate-x-0.5"
                            />
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
