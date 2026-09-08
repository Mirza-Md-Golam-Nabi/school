@php
    $orientation = $get('orientation') ?? 'P';
    $isPortrait = $orientation === 'P';
@endphp

<div class="flex flex-col items-center justify-center gap-2 py-1">
    <div
        @class([
            'flex items-center justify-center rounded border-2 border-primary-400 bg-primary-50 transition-all duration-500 ease-in-out dark:border-primary-500 dark:bg-primary-900/20',
            'h-24 w-16' => $isPortrait,
            'h-16 w-24' => ! $isPortrait,
        ])
    >
        <span class="text-[10px] font-semibold text-primary-600 dark:text-primary-300">A4</span>
    </div>
    <span class="text-[10px] font-medium text-gray-600 dark:text-gray-300 sm:text-xs">
        {{ $isPortrait ? 'Portrait (খাড়া)' : 'Landscape (আড়াআড়ি)' }}
    </span>
</div>
