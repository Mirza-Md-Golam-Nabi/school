<x-filament-panels::page>
    @if ($classes->isEmpty())
        <x-filament::empty-state
            icon="heroicon-o-academic-cap"
            heading="No active classes found"
            description="Add active classes first to assign fee discounts."
        />
    @else

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @foreach ($classes as $class)
                @php
                    $accent         = \App\Support\ClassAccentColor::for($class->id);
                    $count          = $class->discounted_students_count;
                    $url            = App\Filament\Resources\StudentFeeDiscounts\StudentFeeDiscountResource::getUrl(
                                          'class-discounts', ['class' => $class->id]
                                      );
                    $visibleStats   = $class->discountStats->take(3);
                    $hiddenCount    = max(0, $class->discountStats->count() - 3);
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
                                :color="$count > 0 ? 'success' : 'gray'"
                            >
                                {{ $count }} {{ Str::plural('student', $count) }}
                            </x-filament::badge>
                        </div>
                    </div>

                    {{-- Discount stats (max 3 discount types) --}}
                    <div class="flex-1 space-y-1 px-3 pb-2">
                        @forelse ($visibleStats as $stat)
                            <div class="flex items-center gap-1.5 rounded bg-gray-50 dark:bg-gray-800">
                                <span class="inline-block h-1 w-1 shrink-0 rounded-full"
                                      style="background-color:{{ $accent }}"></span>
                                <span class="truncate text-[11px] text-gray-600 dark:text-gray-300">
                                    {{ $stat->name }}
                                </span>
                                <x-filament::badge size="sm" :color="$stat->color" class="ml-auto shrink-0">
                                    {{ $stat->student_count }}
                                </x-filament::badge>
                            </div>
                        @empty
                            <div class="rounded border border-dashed border-gray-200 px-2 py-3 text-center dark:border-gray-700">
                                <p class="text-[11px] text-gray-400 dark:text-gray-500">No discounts assigned</p>
                            </div>
                        @endforelse

                        @if ($hiddenCount > 0)
                            <p class="pt-0.5 text-center text-[10px] text-gray-400 dark:text-gray-500">
                                +{{ $hiddenCount }} more — click to view all
                            </p>
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center justify-between border-t border-gray-100 px-3 py-2 dark:border-gray-800">
                        @if ($count > 0)
                            <div>
                                <p class="text-xs font-bold text-gray-900 dark:text-white">
                                    {{ $count }} / {{ $class->active_students_count }}
                                </p>
                                <p class="text-[10px] leading-tight text-gray-500 dark:text-gray-400">
                                    Students with discount
                                </p>
                            </div>
                        @else
                            <p class="text-[10px] text-gray-400 dark:text-gray-500">Not assigned yet</p>
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
