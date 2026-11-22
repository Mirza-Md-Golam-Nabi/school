<div class="pwa-login" data-pwa-hide-when-installed>
    <a href="{{ $guideUrl }}" class="pwa-login__btn" data-pwa-install>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M12 7.5v6"/><path d="m9.5 11 2.5 2.5 2.5-2.5"/><path d="M10.5 18.5h3"/></svg>
        {{ __('Install app') }}
    </a>
    <p class="pwa-login__hint">
        {{ __('Keep the app on your home screen and open it with one tap.') }}
        <a href="{{ $guideUrl }}">{{ __('How to install?') }}</a>
    </p>
</div>
