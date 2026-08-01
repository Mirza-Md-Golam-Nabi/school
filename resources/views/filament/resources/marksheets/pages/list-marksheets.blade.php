@php
    use App\Filament\Resources\Marksheets\MarksheetResource;
    use App\Support\ClassAccentColor;

    $statusMeta = [
        'generated' => ['label' => 'Generated', 'color' => 'success'],
        'pending' => ['label' => 'Pending', 'color' => 'warning'],
    ];
@endphp

<x-filament-panels::page>
    @if ($classes->isEmpty())
        <x-filament::empty-state
            icon="heroicon-o-academic-cap"
            heading="No active classes found"
            description="Add active classes first to manage marksheets."
        />
    @else
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @foreach ($classes as $class)
                @php
                    $accent = ClassAccentColor::for($class->id);
                    $url    = MarksheetResource::getUrl('class-marksheets', ['class' => $class->id]);
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
                            <x-heroicon-o-document-text class="h-4 w-4" style="color:{{ $accent }}" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-bold text-gray-900 sm:text-sm dark:text-white">
                                {{ $class->name }}
                            </p>
                            <x-filament::badge
                                size="sm"
                                :color="$class->total_marksheets > 0 ? 'primary' : 'gray'"
                            >
                                {{ $class->total_marksheets }} {{ Str::plural('sheet', $class->total_marksheets) }}
                            </x-filament::badge>
                        </div>
                    </div>

                    {{-- Status breakdown --}}
                    <div class="flex-1 space-y-1 px-3 pb-2">
                        @if ($class->total_marksheets > 0)
                            @foreach ($statusMeta as $key => $meta)
                                @php $count = $class->marksheetStats->get($key, 0); @endphp
                                @if ($count > 0)
                                    <div class="flex items-center justify-between rounded bg-gray-50 px-2 py-1 dark:bg-gray-800">
                                        <span class="flex items-center gap-1.5 text-xs text-gray-600 sm:text-[11px] dark:text-gray-300">
                                            <span class="inline-block h-1 w-1 shrink-0 rounded-full"
                                                  style="background-color:{{ $accent }}"></span>
                                            {{ $meta['label'] }}
                                        </span>
                                        <x-filament::badge size="sm" :color="$meta['color']">
                                            {{ $count }}
                                        </x-filament::badge>
                                    </div>
                                @endif
                            @endforeach
                        @else
                            <div class="rounded border border-dashed border-gray-200 px-2 py-3 text-center dark:border-gray-700">
                                <p class="text-xs text-gray-400 sm:text-[11px] dark:text-gray-500">No marksheets yet</p>
                            </div>
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center justify-between border-t border-gray-100 px-3 py-2 dark:border-gray-800">
                        @if ($class->pending_count > 0)
                            <div>
                                <p class="text-xs font-bold text-warning-600 dark:text-warning-400">
                                    {{ $class->pending_count }} {{ Str::plural('sheet', $class->pending_count) }}
                                </p>
                                <p class="text-xs leading-tight text-gray-500 sm:text-[10px] dark:text-gray-400">
                                    Pending
                                </p>
                            </div>
                        @elseif ($class->total_marksheets > 0)
                            <div>
                                <p class="text-xs font-bold text-success-600 dark:text-success-400">All generated</p>
                                <p class="text-xs leading-tight text-gray-500 sm:text-[10px] dark:text-gray-400">No pending</p>
                            </div>
                        @else
                            <p class="text-xs text-gray-400 sm:text-[10px] dark:text-gray-500">Not generated</p>
                        @endif

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
