<x-filament-widgets::widget>
    <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-4">
        <div class="flex items-center gap-x-2 sm:gap-x-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-950 sm:h-14 sm:w-14">
                <x-heroicon-o-megaphone class="h-5 w-5 text-primary-600 dark:text-primary-400 sm:h-7 sm:w-7" />
            </div>
            <div class="min-w-0">
                <p class="text-xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-2xl">{{ __('Notices') }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 sm:text-sm">{{ __('Latest announcements') }}</p>
            </div>
        </div>

        <div class="mt-3 divide-y divide-gray-100 border-t border-gray-200 dark:divide-gray-800 dark:border-white/10 sm:mt-4">
            @forelse ($notices as $notice)
                <div class="py-2 first:pt-3 last:pb-0">
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
            @empty
                <p class="py-3 text-center text-xs text-gray-400 dark:text-gray-500">{{ __('No notices yet.') }}</p>
            @endforelse
        </div>

        <div class="mt-3 sm:mt-4">
            <a href="{{ $url }}" class="block">
                <x-filament::button color="gray" size="sm" class="w-full">
                    {{ __('See More') }}
                </x-filament::button>
            </a>
        </div>
    </div>
</x-filament-widgets::widget>
