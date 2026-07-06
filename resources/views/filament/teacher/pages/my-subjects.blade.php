<x-filament-panels::page>
    <div class="space-y-3">

        @php
            $classes = $this->classesBySubjects;
            $totalSubjects = $classes->sum(fn($item) => $item['subjects']->count());
        @endphp

        {{-- Filter Card --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">

            {{-- Header row: title + counts --}}
            <div class="flex items-center gap-x-2 border-b border-gray-100 px-3 py-2.5 dark:border-gray-800">
                <x-heroicon-o-book-open class="h-4 w-4 text-primary-500" />
                <span class="text-sm font-semibold text-gray-900 dark:text-white">My Subjects</span>
                <div class="ml-auto flex items-center gap-2">
                    <x-filament::badge color="primary">
                        {{ $classes->count() }} {{ Str::plural('class', $classes->count()) }}
                    </x-filament::badge>
                    <x-filament::badge color="gray">
                        {{ $totalSubjects }} {{ Str::plural('subject', $totalSubjects) }}
                    </x-filament::badge>
                </div>
            </div>

            {{-- Session selector row --}}
            <div class="flex flex-row items-center gap-2 px-3 py-2">
                <span
                    class="shrink-0 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Session
                    Year</span>
                <select wire:model.live="sessionYear"
                    class="w-full rounded-lg border-gray-300 py-1.5 px-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:w-40 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    @foreach ($this->getSessionYearOptions() as $year => $label)
                        <option value="{{ $year }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Class Cards --}}
        @if ($classes->isEmpty())
            <x-filament::empty-state icon="heroicon-o-book-open">
                <x-slot name="heading">No subjects assigned</x-slot>
                <x-slot name="description">Your subject assignments for {{ $sessionYear }} will appear here once added
                    by the admin.</x-slot>
            </x-filament::empty-state>
        @else
            <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 md:grid-cols-3">
                @php
                    $accents = \App\Enums\CardAccentColor::cases();
                @endphp

                @foreach ($classes as $item)
                    @php
                        $class = $item['class'];
                        $subjects = $item['subjects'];
                        $count = $subjects->count();
                        $accent = $accents[$loop->index % count($accents)];
                    @endphp

                    <div
                        class="group flex flex-col gap-y-2 rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-950/5 transition-all duration-150 hover:shadow-md dark:bg-gray-900 dark:ring-white/10">

                        {{-- Class header --}}
                        <div class="flex items-center gap-x-2">
                            <div
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md {{ $accent->iconBackground() }}">
                                <x-heroicon-o-academic-cap class="h-3.5 w-3.5 {{ $accent->iconColor() }}" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-semibold text-gray-900 dark:text-white">
                                    {{ $class?->name }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500">Class</p>
                            </div>
                            <x-filament::badge :color="$accent->getColor()">
                                {{ $count }}
                            </x-filament::badge>
                        </div>

                        {{-- Subjects list --}}
                        <div class="space-y-1 border-t border-gray-100 pt-2 dark:border-gray-800">
                            @foreach ($subjects as $subject)
                                <div class="flex items-center justify-between gap-x-2">
                                    <div class="flex min-w-0 items-center gap-x-1.5">
                                        <div class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $accent->dotColor() }}"></div>
                                        <span
                                            class="truncate text-xs text-gray-600 dark:text-gray-300">{{ $subject['name'] }}</span>
                                    </div>
                                    @if ($subject['section'])
                                        <span
                                            class="shrink-0 text-xs font-medium text-gray-400 dark:text-gray-500">{{ $subject['section'] }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        {{-- Footer --}}
                        <div
                            class="flex items-center justify-between border-t border-gray-100 pt-1 dark:border-gray-800">
                            <span class="text-xs text-gray-400 dark:text-gray-500">Session {{ $sessionYear }}</span>
                            <span class="text-xs font-medium text-gray-600 dark:text-gray-300">{{ $count }}
                                {{ Str::plural('subject', $count) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
