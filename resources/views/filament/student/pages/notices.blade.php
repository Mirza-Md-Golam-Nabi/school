<x-filament-panels::page>
    @php $notices = $this->getNotices(); @endphp

    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        @if ($notices->isEmpty())
            <x-filament::empty-state icon="heroicon-o-megaphone">
                <x-slot name="heading">No notices yet.</x-slot>
            </x-filament::empty-state>
        @else
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($notices as $notice)
                    <div class="flex items-center justify-between gap-x-3 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <x-filament::modal width="2xl">
                                <x-slot name="trigger">
                                    <button
                                        type="button"
                                        wire:click="markAsRead({{ $notice->id }})"
                                        class="block w-full truncate text-left text-xs sm:text-sm {{ $notice->is_read ? 'font-normal text-gray-600 dark:text-gray-400' : 'font-bold text-gray-900 dark:text-white' }}"
                                    >
                                        {{ $notice->title }}
                                    </button>
                                </x-slot>
                                <x-slot name="heading">{{ $notice->title }}</x-slot>
                                {!! $this->getNoticeDetail($notice->id) !!}
                            </x-filament::modal>
                        </div>

                        <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500">
                            {{ $notice->published_at?->format('d M Y') }}
                        </span>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-gray-100 px-4 py-3 dark:border-gray-800">
                {{ $notices->links() }}
            </div>
        @endif
    </div>
</x-filament-panels::page>
