<x-filament-panels::page>
    @php $histories = $this->getHistories(); @endphp

    @if ($histories->isEmpty())
        <x-filament::empty-state icon="heroicon-o-archive-box">
            <x-slot name="heading">No previous academic history yet.</x-slot>
        </x-filament::empty-state>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
            @foreach ($histories as $history)
                <a
                    href="{{ \App\Filament\Student\Pages\AcademicHistoryClass::getUrl(['history' => $history->id], panel: 'student') }}"
                    class="group flex flex-col gap-y-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-950/5 transition-all duration-150 hover:shadow-md dark:bg-gray-900 dark:ring-white/10"
                >
                    <div class="flex items-center gap-x-2">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary-50 dark:bg-primary-950">
                            <x-heroicon-o-academic-cap class="h-4 w-4 text-primary-600 dark:text-primary-400" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <h3 class="truncate text-xs font-semibold text-gray-900 dark:text-white">
                                {{ $history->class?->name ?? '—' }}
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $history->session_year }}</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <x-filament::badge :color="$history->status->getColor()" size="sm">
                            {{ $history->status->getLabel() }}
                        </x-filament::badge>

                        <x-heroicon-o-arrow-right
                            class="h-4 w-4 text-gray-300 transition-colors duration-150 group-hover:text-primary-500 dark:text-gray-600 dark:group-hover:text-primary-400"
                        />
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
