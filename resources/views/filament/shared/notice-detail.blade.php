<div class="space-y-3">
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
        <span class="inline-flex items-center gap-x-1">
            <x-heroicon-o-calendar-days class="h-3.5 w-3.5" />
            {{ $notice->published_at?->format('d M Y, h:i A') }}
        </span>
        @if ($notice->createdBy)
            <span class="inline-flex items-center gap-x-1">
                <x-heroicon-o-user class="h-3.5 w-3.5" />
                {{ $notice->createdBy->name }}
            </span>
        @endif
    </div>

    <div class="rounded-lg border border-gray-100 bg-gray-50 p-4 text-sm leading-relaxed text-gray-700 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-300">
        {!! $notice->body !!}
    </div>
</div>
