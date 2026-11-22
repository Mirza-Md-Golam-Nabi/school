<a
    href="{{ $guideUrl }}"
    class="pwa-topbar-btn"
    data-pwa-install
    data-pwa-hide-when-installed
    title="{{ __('Install app') }}"
    aria-label="{{ __('Install app') }}"
>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M12 7.5v6"/><path d="m9.5 11 2.5 2.5 2.5-2.5"/><path d="M10.5 18.5h3"/></svg>
    <span class="pwa-topbar-btn__label">{{ __('Install app') }}</span>
</a>
