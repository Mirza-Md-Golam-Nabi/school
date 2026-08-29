<div class="flex items-center gap-x-2 px-3 pb-2">
    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
        {{ __('Language') }}
    </span>

    <div class="fi-theme-switcher">
        @foreach (config('app.available_locales') as $code => $label)
            <a
                href="{{ route('locale.switch', $code) }}"
                @class([
                    'fi-theme-switcher-btn !w-auto px-3 text-xs font-semibold hover:bg-gray-200 focus-visible:bg-gray-200',
                    'fi-active' => app()->isLocale($code),
                ])
            >
                {{ $label }}
            </a>
        @endforeach
    </div>
</div>
