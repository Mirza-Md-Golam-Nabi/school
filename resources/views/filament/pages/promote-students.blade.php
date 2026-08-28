<x-filament-panels::page>
    <div class="mb-4 max-w-xs">
        <label for="promote-year" class="text-sm font-medium text-gray-700 dark:text-gray-200">
            Session Year
        </label>
        <select
            id="promote-year"
            wire:model.live="year"
            class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
        >
            @foreach ($this->getYearOptions() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    @php $classes = $this->getClasses(); @endphp

    @if ($classes->isEmpty())
        <x-filament::empty-state icon="heroicon-o-arrow-trending-up">
            <x-slot name="heading">No active classes available.</x-slot>
        </x-filament::empty-state>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-5">
            @foreach ($classes as $class)
                <a
                    href="{{ \App\Filament\Pages\PromoteStudentsForClass::getUrl(['classId' => $class->id, 'year' => $year]) }}"
                    class="group flex flex-col gap-y-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-950/5 transition-all duration-150 hover:shadow-md dark:bg-gray-900 dark:ring-white/10"
                >
                    <div class="flex items-center gap-x-2">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary-50 dark:bg-primary-950">
                            <x-heroicon-o-academic-cap class="h-4 w-4 text-primary-600 dark:text-primary-400" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <h3 class="truncate text-xs font-semibold text-gray-900 dark:text-white">
                                {{ $class->name }}
                            </h3>
                            <x-filament::badge :color="$class->level->getColor()" size="sm">
                                {{ $class->level->getLabel() }}
                            </x-filament::badge>
                        </div>
                    </div>

                    <div class="flex items-end justify-between">
                        <div>
                            <p class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                                {{ $class->active_students_count }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Students ({{ $year }})</p>
                        </div>

                        <x-heroicon-o-arrow-right
                            class="h-4 w-4 text-gray-300 transition-colors duration-150 group-hover:text-primary-500 dark:text-gray-600 dark:group-hover:text-primary-400"
                        />
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
