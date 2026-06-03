<x-filament-panels::page>
    @if ($classes->isEmpty())
        <x-filament::empty-state
            icon="heroicon-o-academic-cap"
            heading="No active classes found"
            description="Add active classes first to configure fee structures."
        />
    @else

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @foreach ($classes as $class)
                @php
                    $accent          = \App\Support\ClassAccentColor::for($class->id);
                    $url             = App\Filament\Resources\FeeStructures\FeeStructureResource::getUrl(
                                           'class-fee-structures', ['class' => $class->id]
                                       );
                    $monthly         = $class->feeStructures->filter(fn ($s) => $s->feeType->is_monthly);
                    $visible         = $monthly->take(3);
                    $hiddenCount     = max(0, $monthly->count() - 3);
                    $monthlyTotal    = $class->feeStructures
                                           ->filter(fn ($s) => $s->feeType->is_monthly)
                                           ->sum('amount');
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
                            <x-heroicon-o-academic-cap class="h-4 w-4" style="color:{{ $accent }}" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-gray-900 dark:text-white">
                                {{ $class->name }}
                            </p>
                            <x-filament::badge
                                size="sm"
                                :color="$class->fee_structures_count > 0 ? 'success' : 'gray'"
                            >
                                {{ $class->fee_structures_count }}
                                {{ Str::plural('type', $class->fee_structures_count) }}
                            </x-filament::badge>
                        </div>
                    </div>

                    {{-- Fee type rows (max 3 visible) --}}
                    <div class="flex-1 space-y-1 px-3 pb-2">
                        @forelse ($visible as $structure)
                            <div class="flex items-center justify-between rounded bg-gray-50 dark:bg-gray-800">
                                <span class="flex items-center gap-1.5 text-[11px] text-gray-600 dark:text-gray-300">
                                    <span class="inline-block h-1 w-1 shrink-0 rounded-full"
                                          style="background-color:{{ $accent }}"></span>
                                    {{ $structure->feeType->name }}
                                </span>
                                <x-filament::badge size="sm" color="gray">
                                    ৳{{ number_format($structure->amount, 0) }}
                                </x-filament::badge>
                            </div>
                        @empty
                            <div class="rounded border border-dashed border-gray-200 px-2 py-3 text-center dark:border-gray-700">
                                <p class="text-[11px] text-gray-400 dark:text-gray-500">No fee types added</p>
                            </div>
                        @endforelse

                        @if ($hiddenCount > 0)
                            <p class="pt-0.5 text-center text-[10px] text-gray-400 dark:text-gray-500">
                                +{{ $hiddenCount }} more — click to view all
                            </p>
                        @endif
                    </div>

                    {{-- Footer: monthly total only --}}
                    <div class="flex items-center justify-between border-t border-gray-100 px-3 py-2 dark:border-gray-800">
                        @if ($monthlyTotal > 0)
                            <div>
                                <p class="text-xs font-bold text-gray-900 dark:text-white">
                                    ৳{{ number_format($monthlyTotal, 0) }}
                                </p>
                                <p class="text-[10px] leading-tight text-gray-500 dark:text-gray-400">
                                    Monthly total
                                </p>
                            </div>
                        @else
                            <p class="text-[10px] text-gray-400 dark:text-gray-500">Not configured</p>
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
