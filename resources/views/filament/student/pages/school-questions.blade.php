<x-filament-panels::page>
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <iframe
            src="{{ $this->questionsUrl() }}"
            title="{{ __('School questions') }}"
            class="block w-full border-0"
            style="height: 80vh"
            loading="lazy"
        ></iframe>
    </div>
</x-filament-panels::page>
