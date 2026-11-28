{{--
    Shared "install the app" controller for the admin, student and teacher PWAs.

    Every install button is a plain link to the install guide page carrying
    `data-pwa-install`. Where the browser offers a native install prompt
    (Android / desktop Chrome & Edge) the click is intercepted and the prompt
    is shown; elsewhere (iPhone Safari, in-app browsers, Firefox) the link
    simply opens the guide with step-by-step instructions.

    Expects: $panelId ('admin' | 'student' | 'teacher'), $guideUrl.
--}}
@once
    <script>
        (function () {
            if (window.pwaInstall) {
                return;
            }

            var panelId = @js($panelId);
            var root = document.documentElement;
            var userAgent = navigator.userAgent || '';
            var installedKey = 'pwa-installed:' + panelId;
            var dismissedKey = 'pwa-banner-dismissed:' + panelId;
            var iosConfirmedKey = 'pwa-ios-confirmed:' + panelId;
            var dayMs = 24 * 60 * 60 * 1000;
            var bannerSnoozeMs = 7 * dayMs;
            var iosConfirmedSnoozeMs = 60 * dayMs;
            var deferredPrompt = null;

            var store = {
                get: function (key) { try { return localStorage.getItem(key); } catch (e) { return null; } },
                set: function (key, value) { try { localStorage.setItem(key, value); } catch (e) {} },
                remove: function (key) { try { localStorage.removeItem(key); } catch (e) {} },
            };

            var isStandalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
                || navigator.standalone === true;

            var platform = /FBAN|FBAV|FB_IAB|FBIOS|Instagram|Messenger|MicroMessenger|Line\/|; wv\)/i.test(userAgent)
                ? 'inapp'
                : (/iphone|ipad|ipod/i.test(userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1))
                    ? 'ios'
                    : /android/i.test(userAgent) ? 'android' : 'desktop';

            function elapsedSince(key) {
                return Date.now() - (Number(store.get(key)) || 0);
            }

            /**
             * Chromium reports both install ('appinstalled') and uninstall (it
             * fires 'beforeinstallprompt' again), so the stored flag is reliable
             * there. iOS reports neither — it never sets this flag.
             */
            function isInstalled() {
                return isStandalone || store.get(installedKey) === '1';
            }

            function isBannerAllowed() {
                if (isInstalled()) {
                    return false;
                }

                if (platform === 'ios' && elapsedSince(iosConfirmedKey) < iosConfirmedSnoozeMs) {
                    return false;
                }

                return elapsedSince(dismissedKey) > bannerSnoozeMs;
            }

            function refresh() {
                root.dataset.pwaPlatform = platform;
                root.classList.toggle('pwa-standalone', isStandalone);
                root.classList.toggle('pwa-installed', isInstalled());
                root.classList.toggle('pwa-banner-on', isBannerAllowed());
                root.classList.toggle('pwa-can-prompt', deferredPrompt !== null);
            }

            function markInstalled() {
                store.set(installedKey, '1');
                refresh();
            }

            refresh();

            window.addEventListener('beforeinstallprompt', function (event) {
                event.preventDefault();
                deferredPrompt = event;
                store.remove(installedKey);
                refresh();
            });

            window.addEventListener('appinstalled', function () {
                deferredPrompt = null;
                markInstalled();
            });

            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function () {
                    navigator.serviceWorker.register('/sw.js').catch(function () {});
                });
            }

            document.addEventListener('click', function (event) {
                var target = event.target instanceof Element ? event.target : null;

                if (!target) {
                    return;
                }

                if (target.closest('[data-pwa-dismiss]')) {
                    event.preventDefault();
                    store.set(dismissedKey, String(Date.now()));
                    refresh();

                    return;
                }

                if (target.closest('[data-pwa-confirm-installed]')) {
                    event.preventDefault();
                    store.set(iosConfirmedKey, String(Date.now()));
                    refresh();

                    return;
                }

                if (target.closest('[data-pwa-reset]')) {
                    event.preventDefault();
                    store.remove(installedKey);
                    refresh();

                    return;
                }

                if (!target.closest('[data-pwa-install]')) {
                    return;
                }

                if (deferredPrompt) {
                    event.preventDefault();
                    var installPrompt = deferredPrompt;
                    deferredPrompt = null;
                    installPrompt.prompt();
                    installPrompt.userChoice.then(function (choice) {
                        if (choice.outcome === 'accepted') {
                            markInstalled();
                        } else {
                            refresh();
                        }
                    });

                    return;
                }

                var guide = document.querySelector('[data-pwa-guide]');

                if (guide) {
                    event.preventDefault();
                    var steps = document.querySelector('[data-pwa-steps="' + platform + '"]') || guide;
                    steps.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    steps.classList.add('is-flash');
                    setTimeout(function () { steps.classList.remove('is-flash'); }, 1800);
                }
            }, true);

            window.pwaInstall = { panelId: panelId, platform: platform, isStandalone: isStandalone, guideUrl: @js($guideUrl) };
        })();
    </script>
@endonce
