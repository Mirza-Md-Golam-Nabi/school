@php
    use App\Filament\Teacher\Resources\Exams\ExamResource;
    use App\Support\ClassAccentColor;
@endphp

<x-filament-panels::page>
    @if ($classes->isEmpty())
        <x-filament::empty-state
            icon="heroicon-o-clipboard-document-check"
            heading="No assigned classes"
            description="You are not assigned to teach a subject in any class yet."
        />
    @else
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @foreach ($classes as $class)
                @php
                    $accent = ClassAccentColor::for($class->id);
                    $url    = ExamResource::getUrl('class-exams', ['class' => $class->id]);
                @endphp

                <a
                    href="{{ $url }}"
                    class="group flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 transition-all duration-200 hover:-translate-y-1 hover:shadow-lg dark:bg-gray-900 dark:ring-white/10"
                >
                    {{-- Accent bar --}}
                    <div class="h-1.5" style="background-color:{{ $accent }}"></div>

                    {{-- Header --}}
                    <div class="flex items-center gap-2 px-3 pt-3 pb-2">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                             style="background-color:{{ $accent }}22">
                            <x-heroicon-o-clipboard-document-check class="h-4 w-4" style="color:{{ $accent }}" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-gray-900 dark:text-white">
                                {{ $class->name }}
                            </p>
                            <x-filament::badge
                                size="sm"
                                :color="$class->total_exams > 0 ? 'primary' : 'gray'"
                            >
                                {{ $class->total_exams }} {{ Str::plural('exam', $class->total_exams) }}
                            </x-filament::badge>
                        </div>
                    </div>

                    {{-- Status breakdown --}}
                    <div class="flex-1 space-y-1 px-3 pb-2">
                        @if ($class->total_exams > 0)
                            @if ($class->published_count > 0)
                                <div class="flex items-center justify-between rounded bg-gray-50 px-2 py-1 dark:bg-gray-800">
                                    <span class="flex items-center gap-1.5 text-[11px] text-gray-600 dark:text-gray-300">
                                        <span class="inline-block h-1 w-1 shrink-0 rounded-full" style="background-color:{{ $accent }}"></span>
                                        Published
                                    </span>
                                    <x-filament::badge size="sm" color="success">
                                        {{ $class->published_count }}
                                    </x-filament::badge>
                                </div>
                            @endif

                            @if ($class->draft_count > 0)
                                <div class="flex items-center justify-between rounded bg-gray-50 px-2 py-1 dark:bg-gray-800">
                                    <span class="flex items-center gap-1.5 text-[11px] text-gray-600 dark:text-gray-300">
                                        <span class="inline-block h-1 w-1 shrink-0 rounded-full" style="background-color:{{ $accent }}"></span>
                                        Draft
                                    </span>
                                    <x-filament::badge size="sm" color="warning">
                                        {{ $class->draft_count }}
                                    </x-filament::badge>
                                </div>
                            @endif
                        @else
                            <div class="rounded border border-dashed border-gray-200 px-2 py-3 text-center dark:border-gray-700">
                                <p class="text-[11px] text-gray-400 dark:text-gray-500">No exams yet</p>
                            </div>
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center justify-end border-t border-gray-100 px-3 py-2 dark:border-gray-800">
                        <x-filament::icon-button
                            icon="heroicon-o-arrow-right"
                            size="sm"
                            color="gray"
                            tag="span"
                            class="transition-transform duration-150 group-hover:translate-x-0.5"
                        />
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
