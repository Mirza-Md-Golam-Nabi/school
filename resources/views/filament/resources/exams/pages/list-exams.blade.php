<x-filament-panels::page>
    @if ($classes->isEmpty())
        <x-filament::empty-state icon="heroicon-o-academic-cap">
            <x-slot name="heading">No active classes available.</x-slot>
        </x-filament::empty-state>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
            @foreach ($classes as $class)
                <a
                    href="{{ App\Filament\Resources\Exams\ExamResource::getUrl('exams-by-class') }}?classId={{ $class->id }}"
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

                    <p class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                        {{ $class->exams_count }}
                        <span class="text-xs font-normal text-gray-500 dark:text-gray-400">
                            {{ Str::plural('Exam', $class->exams_count) }}
                        </span>
                    </p>

                    <div class="flex flex-col gap-y-2 border-t border-gray-200 pt-3 dark:border-white/10">
                        <div class="flex items-center gap-x-1 text-xs sm:text-sm">
                            <x-heroicon-o-check-circle class="h-4 w-4 shrink-0 text-success-600 dark:text-success-400" />
                            <span class="truncate text-gray-500 dark:text-gray-400">Published :</span>
                            <span class="font-semibold text-success-600 dark:text-success-400">{{ $class->published_exams_count }}</span>
                        </div>
                        <div class="flex items-center gap-x-1 text-xs sm:text-sm">
                            <x-heroicon-o-x-circle class="h-4 w-4 shrink-0 text-danger-600 dark:text-danger-400" />
                            <span class="truncate text-gray-500 dark:text-gray-400">Pending :</span>
                            <span class="font-semibold text-danger-600 dark:text-danger-400">{{ $class->pending_exams_count }}</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
