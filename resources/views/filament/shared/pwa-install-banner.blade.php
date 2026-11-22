<div class="pwa-banner" role="dialog" aria-live="polite" aria-label="{{ __('Install the app') }}" data-pwa-hide-when-installed>
    <span class="pwa-banner__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M12 7.5v6"/><path d="m9.5 11 2.5 2.5 2.5-2.5"/><path d="M10.5 18.5h3"/></svg>
    </span>
    <div class="pwa-banner__body">
        <p class="pwa-banner__title">{{ __('Install the app') }}</p>
        <p class="pwa-banner__text">{{ __('Get notices, results and attendance faster — open it with one tap from your home screen.') }}</p>
        <div class="pwa-banner__actions">
            <a href="{{ $guideUrl }}" class="pwa-banner__install" data-pwa-install>{{ __('Install') }}</a>
            <button type="button" class="pwa-banner__later" data-pwa-dismiss>{{ __('Later') }}</button>
            <button type="button" class="pwa-banner__confirm" data-pwa-confirm-installed>{{ __('I have installed it ✓') }}</button>
        </div>
    </div>
    <button type="button" class="pwa-banner__close" data-pwa-dismiss aria-label="{{ __('Close') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
</div>
