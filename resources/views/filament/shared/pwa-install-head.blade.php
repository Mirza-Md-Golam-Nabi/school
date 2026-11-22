@include('filament.shared.pwa-install-script', ['panelId' => $panelId, 'guideUrl' => $guideUrl])

<style>
    html.pwa-installed [data-pwa-hide-when-installed] { display: none !important; }

    .pwa-topbar-btn {
        display: inline-flex; align-items: center; gap: .4rem; height: 2.25rem; padding: 0 .65rem; margin-inline-end: .5rem;
        border-radius: .65rem; font-size: .75rem; font-weight: 600; white-space: nowrap;
        color: var(--primary-700); background: color-mix(in oklab, var(--primary-500) 13%, transparent);
        border: 1px solid color-mix(in oklab, var(--primary-500) 32%, transparent);
        transition: background-color .2s, transform .2s;
    }
    .pwa-topbar-btn:hover { background: color-mix(in oklab, var(--primary-500) 22%, transparent); transform: translateY(-1px); }
    .dark .pwa-topbar-btn { color: var(--primary-300); }
    .pwa-topbar-btn svg { width: 1.1rem; height: 1.1rem; flex: none; }
    .pwa-topbar-btn__label { display: none; }
    @media (min-width: 640px) { .pwa-topbar-btn__label { display: inline; } .pwa-topbar-btn { font-size: .8rem; } }

    .pwa-login { margin-top: 1.25rem; padding-top: 1.1rem; border-top: 1px dashed rgb(148 163 184 / .45); text-align: center; }
    .pwa-login__btn {
        display: flex; align-items: center; justify-content: center; gap: .5rem; width: 100%; padding: .6rem 1rem;
        border-radius: .75rem; font-size: .8rem; font-weight: 600;
        color: var(--primary-700); background: color-mix(in oklab, var(--primary-500) 11%, transparent);
        border: 1px solid color-mix(in oklab, var(--primary-500) 35%, transparent);
        transition: background-color .2s, transform .2s;
    }
    .pwa-login__btn:hover { background: color-mix(in oklab, var(--primary-500) 20%, transparent); transform: translateY(-1px); }
    .dark .pwa-login__btn { color: var(--primary-300); }
    .pwa-login__btn svg { width: 1.15rem; height: 1.15rem; }
    .pwa-login__hint { margin: .55rem 0 0; font-size: .72rem; color: rgb(100 116 139); }
    .dark .pwa-login__hint { color: rgb(148 163 184); }
    .pwa-login__hint a { font-weight: 600; text-decoration: underline; text-underline-offset: 2px; color: var(--primary-600); }
    .dark .pwa-login__hint a { color: var(--primary-400); }
    @media (min-width: 640px) { .pwa-login__btn { font-size: .875rem; } .pwa-login__hint { font-size: .8rem; } }

    .pwa-banner {
        display: none; position: fixed; z-index: 45; left: 12px; right: 12px; bottom: 12px;
        gap: .75rem; align-items: flex-start; padding: .85rem .9rem; border-radius: 1rem;
        background: #fff; color: rgb(15 23 42);
        border: 1px solid color-mix(in oklab, var(--primary-500) 28%, transparent);
        box-shadow: 0 24px 48px -16px rgb(15 23 42 / .35), 0 0 0 4px color-mix(in oklab, var(--primary-500) 8%, transparent);
        animation: pwa-rise .45s cubic-bezier(.2, .8, .2, 1) both;
    }
    html.pwa-banner-on .pwa-banner { display: flex; }
    .dark .pwa-banner { background: rgb(24 24 27); color: rgb(241 245 249); border-color: color-mix(in oklab, var(--primary-400) 30%, transparent); }
    .pwa-banner__icon {
        flex: none; display: grid; place-items: center; width: 2.6rem; height: 2.6rem; border-radius: .8rem; color: #fff;
        background: linear-gradient(135deg, var(--primary-400), var(--primary-600));
        box-shadow: 0 8px 18px -8px var(--primary-600);
    }
    .pwa-banner__icon svg { width: 1.35rem; height: 1.35rem; }
    .pwa-banner__body { flex: 1; min-width: 0; }
    .pwa-banner__title { margin: 0; font-size: .85rem; font-weight: 700; }
    .pwa-banner__text { margin: .2rem 0 .65rem; font-size: .72rem; line-height: 1.5; opacity: .75; }
    .pwa-banner__actions { display: flex; gap: .5rem; }
    .pwa-banner__install, .pwa-banner__later {
        display: inline-flex; align-items: center; padding: .4rem .9rem; border-radius: 999px; font-size: .75rem; font-weight: 600; cursor: pointer;
    }
    .pwa-banner__install { color: #fff; background: var(--primary-600); box-shadow: 0 6px 14px -6px var(--primary-600); }
    .pwa-banner__install:hover { background: var(--primary-500); }
    .pwa-banner__later { background: transparent; border: 1px solid rgb(148 163 184 / .5); color: inherit; }
    .pwa-banner__actions { flex-wrap: wrap; }
    .pwa-banner__confirm { display: none; padding: .4rem .2rem; font-size: .72rem; font-weight: 600; background: transparent; border: 0; color: var(--primary-600); cursor: pointer; text-decoration: underline; text-underline-offset: 2px; }
    .dark .pwa-banner__confirm { color: var(--primary-400); }
    html[data-pwa-platform="ios"] .pwa-banner__confirm { display: inline-flex; }
    .pwa-banner__close { flex: none; padding: .15rem; border-radius: .4rem; opacity: .55; background: transparent; border: 0; color: inherit; cursor: pointer; }
    .pwa-banner__close:hover { opacity: 1; }
    .pwa-banner__close svg { width: 1rem; height: 1rem; }
    @media (min-width: 640px) {
        .pwa-banner { left: auto; right: 24px; bottom: 24px; width: 380px; }
        .pwa-banner__title { font-size: .95rem; }
        .pwa-banner__text, .pwa-banner__install, .pwa-banner__later { font-size: .8rem; }
    }
    @keyframes pwa-rise { from { opacity: 0; transform: translateY(16px); } }
    @media (prefers-reduced-motion: reduce) { .pwa-banner { animation: none; } }
</style>
