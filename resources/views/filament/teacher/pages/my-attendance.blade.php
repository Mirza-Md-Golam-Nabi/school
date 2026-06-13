<x-filament-panels::page>
    @if (! $profile)
        <x-filament::empty-state icon="heroicon-o-user-circle">
            <x-slot name="heading">No teacher profile found for your account.</x-slot>
        </x-filament::empty-state>
    @else

        {{-- Responsive wrapper: single column mobile → two columns md+ --}}
        <div class="grid grid-cols-1 items-start gap-5 md:grid-cols-5">

            {{-- STATUS HERO (left, 3/5 on md+) --}}
            <div class="overflow-hidden rounded-2xl bg-white shadow ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 md:col-span-3">

                {{-- Greeting --}}
                <div class="px-5 pt-5 pb-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $greeting }}, <span class="font-semibold text-gray-800 dark:text-gray-100">{{ auth()->user()->name }}</span>
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ now()->format('l, d F Y') }}</p>
                </div>

                {{-- Big status circle --}}
                <div class="flex flex-col items-center py-8 md:py-12">
                    @if ($current)
                        <x-filament::badge :color="$current['color']" size="sm" class="mb-4">
                            Today's Attendance
                        </x-filament::badge>
                        <div class="flex h-24 w-24 items-center justify-center rounded-full shadow-inner md:h-36 md:w-36 {{ $current['circleBg'] }}">
                            <x-filament::icon :icon="$current['icon']" class="{{ $current['iconClass'] }} md:!h-18 md:!w-18" />
                        </div>
                        <p class="{{ $current['textClass'] }} md:text-3xl">{{ $current['label'] }}</p>
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Tap below to update</p>
                    @else
                        <x-filament::badge color="gray" size="sm" class="mb-4">Today's Attendance</x-filament::badge>
                        <div class="flex h-24 w-24 items-center justify-center rounded-full bg-gray-100 shadow-inner md:h-36 md:w-36 dark:bg-gray-800">
                            <x-filament::icon icon="heroicon-o-question-mark-circle" class="h-12 w-12 text-gray-400 md:!h-18 md:!w-18 dark:text-gray-500" />
                        </div>
                        <p class="mt-3 text-2xl font-bold tracking-tight text-gray-600 md:text-3xl dark:text-gray-300">Not Marked</p>
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Please mark your attendance below</p>
                    @endif
                </div>

                <div class="border-t border-gray-100 dark:border-gray-800"></div>

                {{-- ACTION BUTTONS --}}
                <div class="flex divide-x divide-gray-100 dark:divide-gray-800">
                    @foreach ($buttons as $button)
                        <button
                            wire:click="markAs('{{ $button['value'] }}')"
                            wire:loading.attr="disabled"
                            class="group flex flex-1 flex-col items-center gap-y-1.5 py-4 text-xs font-semibold transition-all duration-150 focus:outline-none md:py-5 md:text-sm {{ $button['btnClass'] }}"
                        >
                            <x-filament::icon :icon="$button['cfg']['icon']" class="h-6 w-6 transition-transform duration-150 group-hover:scale-110 md:h-7 md:w-7" />
                            {{ $button['cfg']['label'] }}
                            <span class="{{ $button['dotClass'] }}"></span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- RECENT HISTORY (right, 2/5 on md+) --}}
            @if ($history->isNotEmpty())
                <div class="rounded-2xl bg-white shadow ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 md:col-span-2">
                    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Recent Attendance</p>
                        <x-filament::badge color="gray" size="sm">Last {{ $history->count() }} records</x-filament::badge>
                    </div>
                    <div class="divide-y divide-gray-50 dark:divide-gray-800/60">
                        @foreach ($history as $record)
                            <div class="flex items-center justify-between px-4 py-3">
                                <div class="flex items-center gap-x-3">
                                    <div class="flex h-9 w-9 shrink-0 flex-col items-center justify-center rounded-lg bg-gray-50 dark:bg-gray-800">
                                        <span class="text-[11px] font-bold leading-none text-gray-700 dark:text-gray-200">{{ $record->date->format('d') }}</span>
                                        <span class="text-[9px] uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $record->date->format('M') }}</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $record->date->format('l') }}</p>
                                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ $record->date->format('d M Y') }}</p>
                                    </div>
                                </div>
                                <x-filament::badge :color="$record->status->getColor()" :icon="$record->status->getIcon()" size="sm">
                                    {{ $record->status->getLabel() }}
                                </x-filament::badge>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    @endif
</x-filament-panels::page>
