<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#060818">
    <meta name="description" content="{{ $schoolName }} — {{ $appLabel }} অ্যাপ ইন্সটল গাইড">
    <title>{{ $appLabel }} অ্যাপ ইন্সটল · {{ $schoolName }}</title>
    <link rel="manifest" href="/manifest/{{ $panelId }}.json">
    <script>
        (() => {
            let stored = null;
            try { stored = localStorage.getItem('home-theme'); } catch (e) {}
            const prefersLight = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches;
            const theme = stored === 'light' || stored === 'dark' ? stored : (prefersLight ? 'light' : 'dark');
            document.documentElement.dataset.theme = theme;
            document.querySelector('meta[name="theme-color"]').content = theme === 'light' ? '#fbfaff' : '#060818';
        })();
    </script>
    @include('filament.shared.pwa-install-script', ['panelId' => $panelId, 'guideUrl' => $guideUrl])
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=hind-siliguri:400,500,600,700|baloo-da-2:600,700,800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #060818; --bg-2: #141649; --ink: #f1f5f9; --muted: #94a3b8; --line: rgb(255 255 255 / .1);
            --card: rgb(255 255 255 / .05); --card-strong: rgb(255 255 255 / .08); --accent: #fbbf24; --accent-ink: #1c1300;
            --brand: linear-gradient(135deg, #fde68a, #fbbf24 45%, #f59e0b); --step: rgb(251 191 36 / .16); --step-ink: #fde68a;
            --ok: #34d399; --shadow: 0 20px 50px -30px rgb(0 0 0 / .8);
            --font: 'Hind Siliguri', 'Nirmala UI', 'Vrinda', system-ui, sans-serif;
            --display: 'Baloo Da 2', 'Hind Siliguri', 'Nirmala UI', system-ui, sans-serif;
            color-scheme: dark;
        }
        :root[data-theme="light"] {
            --bg: #fbfaff; --bg-2: #e4e3ff; --ink: #1e1b4b; --muted: #5b5779; --line: rgb(79 70 229 / .13);
            --card: #fff; --card-strong: #fff; --accent: #7c3aed; --accent-ink: #fff;
            --brand: linear-gradient(135deg, #4f46e5, #7c3aed 50%, #db2777); --step: rgb(124 58 237 / .1); --step-ink: #6d28d9;
            --ok: #059669; --shadow: 0 18px 40px -24px rgb(67 56 202 / .4);
            color-scheme: light;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0; min-height: 100vh; font-family: var(--font); color: var(--ink); font-size: .8rem; line-height: 1.6;
            background: radial-gradient(ellipse at top, var(--bg-2), var(--bg) 60%) fixed, var(--bg);
            -webkit-font-smoothing: antialiased;
        }
        @media (min-width: 640px) { body { font-size: .95rem; } }
        a { color: inherit; text-decoration: none; }
        .wrap { width: 100%; max-width: 880px; margin: 0 auto; padding: 0 16px; }

        .top { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: 1rem 0; }
        .brand { display: flex; align-items: center; gap: .6rem; min-width: 0; font-weight: 700; }
        .brand__mark { flex: none; display: grid; place-items: center; width: 2.2rem; height: 2.2rem; border-radius: .8rem; overflow: hidden; color: #fff; background: linear-gradient(135deg, #818cf8, #4f46e5 55%, #312e81); }
        .brand__mark img { width: 100%; height: 100%; object-fit: cover; }
        .brand__mark svg { width: 1.2rem; height: 1.2rem; }
        .brand__name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .back { flex: none; font-size: .75rem; color: var(--muted); }
        .back:hover { color: var(--ink); }

        .hero { padding: 1.5rem 0 1rem; text-align: center; }
        .hero__icon { display: inline-grid; place-items: center; width: 4rem; height: 4rem; border-radius: 1.3rem; font-size: 2rem; background: var(--card-strong); border: 1px solid var(--line); box-shadow: var(--shadow); }
        .hero h1 { margin: .9rem 0 .4rem; font-family: var(--display); font-weight: 800; font-size: clamp(1.4rem, 5vw, 2.4rem); line-height: 1.25; }
        .hero p { margin: 0 auto; max-width: 52ch; color: var(--muted); }
        .cta { display: flex; flex-wrap: wrap; justify-content: center; gap: .6rem; margin-top: 1.4rem; }
        .btn { display: inline-flex; align-items: center; gap: .45rem; padding: .7rem 1.3rem; border-radius: 999px; font-weight: 700; font-size: .8rem; border: 1px solid transparent; cursor: pointer; transition: transform .2s, box-shadow .2s; }
        @media (min-width: 640px) { .btn { font-size: .92rem; } }
        .btn:hover { transform: translateY(-2px); }
        .btn--primary { color: var(--accent-ink); background: var(--brand); box-shadow: 0 12px 30px -12px var(--accent); }
        .btn--ghost { background: var(--card); border-color: var(--line); }
        .btn svg { width: 1.05rem; height: 1.05rem; }
        .installed { display: none; margin: 1.2rem auto 0; max-width: 28rem; padding: .7rem 1rem; border-radius: 1rem; font-weight: 600; color: var(--ok); background: color-mix(in srgb, var(--ok) 12%, transparent); border: 1px solid color-mix(in srgb, var(--ok) 35%, transparent); }
        html.pwa-installed .installed { display: block; }
        html.pwa-installed [data-pwa-hide-when-installed] { display: none !important; }
        .installed__app, html.pwa-standalone .installed__browser { display: none; }
        html.pwa-standalone .installed__app { display: inline; }
        .installed button { display: block; margin: .35rem auto 0; padding: 0; font: inherit; font-size: .72rem; font-weight: 600; color: var(--muted); background: none; border: 0; text-decoration: underline; cursor: pointer; }

        .perks { display: grid; grid-template-columns: repeat(2, 1fr); gap: .6rem; margin: 1.8rem 0 0; }
        @media (min-width: 640px) { .perks { grid-template-columns: repeat(4, 1fr); } }
        .perk { padding: .8rem; border-radius: 1rem; text-align: center; background: var(--card); border: 1px solid var(--line); }
        .perk b { display: block; font-size: 1.3rem; }
        .perk span { font-size: .72rem; color: var(--muted); }
        @media (min-width: 640px) { .perk span { font-size: .82rem; } }

        .section-title { margin: 2.2rem 0 .3rem; font-family: var(--display); font-weight: 800; font-size: clamp(1.15rem, 3.5vw, 1.6rem); text-align: center; }
        .section-note { margin: 0 0 1.1rem; text-align: center; color: var(--muted); }
        .steps { display: flex; flex-direction: column; gap: .9rem; }
        .device { position: relative; padding: 1rem 1.1rem; border-radius: 1.2rem; background: var(--card); border: 1px solid var(--line); box-shadow: var(--shadow); scroll-margin-top: 1rem; transition: box-shadow .3s, border-color .3s; }
        .device h3 { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; margin: 0 0 .6rem; font-size: .95rem; }
        @media (min-width: 640px) { .device h3 { font-size: 1.1rem; } }
        .device ol { margin: 0; padding: 0; list-style: none; counter-reset: step; display: grid; gap: .5rem; }
        .device li { counter-increment: step; display: flex; gap: .6rem; align-items: flex-start; }
        .device li::before { content: counter(step); flex: none; display: grid; place-items: center; width: 1.5rem; height: 1.5rem; border-radius: 50%; font-size: .72rem; font-weight: 700; color: var(--step-ink); background: var(--step); }
        .device .tip { margin: .7rem 0 0; font-size: .72rem; color: var(--muted); }
        @media (min-width: 640px) { .device .tip { font-size: .82rem; } }
        .key { display: inline-block; padding: 0 .4rem; border-radius: .35rem; font-weight: 600; background: var(--step); color: var(--step-ink); white-space: nowrap; }
        .yours { display: none; padding: .1rem .55rem; border-radius: 999px; font-size: .65rem; font-weight: 700; color: var(--accent-ink); background: var(--brand); }
        .device.is-flash { border-color: var(--accent); box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 25%, transparent), var(--shadow); }
        .copy { margin-top: .7rem; }
        @foreach (['android', 'ios', 'desktop', 'inapp'] as $platform)
        html[data-pwa-platform="{{ $platform }}"] [data-pwa-steps="{{ $platform }}"] { order: -1; border-color: color-mix(in srgb, var(--accent) 55%, transparent); }
        html[data-pwa-platform="{{ $platform }}"] [data-pwa-steps="{{ $platform }}"] .yours { display: inline-block; }
        @endforeach

        .faq { display: grid; gap: .6rem; margin-bottom: 2rem; }
        .faq details { padding: .75rem 1rem; border-radius: 1rem; background: var(--card); border: 1px solid var(--line); }
        .faq summary { cursor: pointer; font-weight: 600; }
        .faq p { margin: .5rem 0 0; color: var(--muted); }
        .foot { padding: 1.5rem 0 2rem; border-top: 1px solid var(--line); text-align: center; font-size: .72rem; color: var(--muted); }
        @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } .btn { transition: none; } }
    </style>
</head>
<body>
    <header class="wrap top">
        <a href="{{ route('home') }}" class="brand">
            <span class="brand__mark">
                @if ($schoolLogoUrl)
                    <img src="{{ $schoolLogoUrl }}" alt="{{ $schoolName }}">
                @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.5 2.7 3 6 3s6-1.5 6-3v-5"/><path d="M22 10v6"/></svg>
                @endif
            </span>
            <span class="brand__name">{{ $schoolName }}</span>
        </a>
        <a href="{{ route('home') }}" class="back">← হোম</a>
    </header>

    <main class="wrap" data-pwa-guide>
        <section class="hero">
            <span class="hero__icon">{{ $panelId === 'student' ? '🎓' : '👩‍🏫' }}</span>
            <h1>{{ $appLabel }} অ্যাপ ইন্সটল করুন</h1>
            <p>Play Store লাগবে না — কয়েক সেকেন্ডেই ফোন বা কম্পিউটারে অ্যাপটি যুক্ত হবে। এরপর হোম স্ক্রিনের আইকনে ট্যাপ করলেই সরাসরি {{ $appLabel }} প্যানেল খুলবে।</p>

            <div class="installed">
                <span class="installed__app">✅ অ্যাপটি ইন্সটল করা আছে — আপনি অ্যাপ থেকেই দেখছেন।</span>
                <span class="installed__browser">✅ এই ডিভাইসে অ্যাপটি ইন্সটল করা আছে — হোম স্ক্রিনের আইকন থেকে খুলুন।</span>
                <button type="button" class="installed__browser" data-pwa-reset>অ্যাপ মুছে ফেলেছেন? আবার ইন্সটল করুন</button>
            </div>

            <div class="cta">
                <a href="#steps" class="btn btn--primary" data-pwa-install data-pwa-hide-when-installed>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M12 7.5v6"/><path d="m9.5 11 2.5 2.5 2.5-2.5"/></svg>
                    এখনই ইন্সটল করুন
                </a>
                <a href="{{ $loginUrl }}" class="btn btn--ghost">লগইন পেজে যান</a>
            </div>

            <div class="perks">
                <div class="perk"><b>⚡</b><span>এক ট্যাপে দ্রুত খোলে</span></div>
                <div class="perk"><b>🔔</b><span>নোটিশ ও রেজাল্টের নোটিফিকেশন</span></div>
                <div class="perk"><b>📦</b><span>ফোনে প্রায় কোনো জায়গা নেয় না</span></div>
                <div class="perk"><b>🔄</b><span>সবসময় সর্বশেষ সংস্করণ</span></div>
            </div>
        </section>

        <h2 class="section-title" id="steps">কীভাবে ইন্সটল করবেন</h2>
        <p class="section-note">আপনার ডিভাইসের নির্দেশনাটি সবার উপরে দেখানো হয়েছে।</p>

        <div class="steps">
            <section class="device" data-pwa-steps="android">
                <h3>🤖 Android ফোন (Chrome) <span class="yours">আপনার ডিভাইস</span></h3>
                <ol>
                    <li>এই পেজটি <span class="key">Chrome</span> ব্রাউজারে খুলুন।</li>
                    <li>উপরের <span class="key">এখনই ইন্সটল করুন</span> বাটনে চাপুন।</li>
                    <li>যে বক্স আসবে সেখানে <span class="key">Install / ইন্সটল</span> চাপুন।</li>
                </ol>
                <p class="tip">বাটনে কাজ না হলে: Chrome-এর উপরে ডানের <span class="key">⋮</span> মেনু → <span class="key">Install app</span> বা <span class="key">Add to Home screen</span>।</p>
            </section>

            <section class="device" data-pwa-steps="ios">
                <h3>🍎 iPhone / iPad (Safari) <span class="yours">আপনার ডিভাইস</span></h3>
                <ol>
                    <li>এই পেজটি <span class="key">Safari</span> ব্রাউজারে খুলুন।</li>
                    <li>নিচের <span class="key">Share ⬆️</span> বাটনে চাপুন (বর্গের ভেতরে উপরমুখী তীর)।</li>
                    <li>তালিকা থেকে <span class="key">Add to Home Screen</span> বেছে নিন।</li>
                    <li>উপরে ডানে <span class="key">Add</span> চাপুন — হোম স্ক্রিনে অ্যাপের আইকন চলে আসবে।</li>
                </ol>
                <p class="tip">iPhone-এ শুধু Safari থেকেই ইন্সটল করা যায়।</p>
            </section>

            <section class="device" data-pwa-steps="desktop">
                <h3>💻 কম্পিউটার (Chrome / Edge) <span class="yours">আপনার ডিভাইস</span></h3>
                <ol>
                    <li>এই পেজটি <span class="key">Chrome</span> বা <span class="key">Edge</span> ব্রাউজারে খুলুন।</li>
                    <li><span class="key">এখনই ইন্সটল করুন</span> বাটন অথবা অ্যাড্রেস বারের ডানের ইন্সটল আইকনে <span class="key">⊕</span> চাপুন।</li>
                    <li><span class="key">Install</span> চাপুন — ডেস্কটপ ও স্টার্ট মেনুতে অ্যাপ যুক্ত হবে।</li>
                </ol>
                <p class="tip">Firefox ব্রাউজারে অ্যাপ ইন্সটল সাপোর্ট করে না।</p>
            </section>

            <section class="device" data-pwa-steps="inapp">
                <h3>💬 Facebook / Messenger থেকে খুলেছেন? <span class="yours">আপনার ডিভাইস</span></h3>
                <ol>
                    <li>এই অ্যাপগুলোর ভেতরের ব্রাউজারে ইন্সটল হয় না।</li>
                    <li>উপরের <span class="key">⋮</span> বা <span class="key">•••</span> মেনুতে চাপুন।</li>
                    <li><span class="key">Open in Chrome</span> / <span class="key">Open in browser</span> বেছে নিন, তারপর উপরের ধাপগুলো অনুসরণ করুন।</li>
                </ol>
                <button type="button" class="btn btn--ghost copy" data-copy-link="{{ $guideUrl }}">🔗 লিংক কপি করুন</button>
            </section>
        </div>

        <h2 class="section-title">সাধারণ প্রশ্ন</h2>
        <div class="faq">
            <details>
                <summary>অ্যাপ আপডেট করতে হবে কি?</summary>
                <p>না। অ্যাপটি সবসময় নিজে থেকেই সর্বশেষ সংস্করণে থাকে।</p>
            </details>
            <details>
                <summary>অ্যাপ কীভাবে মুছবেন?</summary>
                <p>অন্য যেকোনো অ্যাপের মতোই — আইকনে চেপে ধরে <b>Uninstall / Remove</b> চাপুন।</p>
            </details>
            <details>
                <summary>লগইন আইডি বা পাসওয়ার্ড জানা নেই?</summary>
                <p>স্কুল অফিসে যোগাযোগ করুন।</p>
            </details>
        </div>
    </main>

    <footer class="foot">© {{ now()->year }} {{ $schoolName }}</footer>

    <script>
        document.querySelectorAll('[data-copy-link]').forEach((button) => {
            button.addEventListener('click', async () => {
                const link = button.dataset.copyLink;
                try {
                    await navigator.clipboard.writeText(link);
                    button.textContent = '✅ লিংক কপি হয়েছে';
                } catch (e) {
                    window.prompt('লিংকটি কপি করুন:', link);
                }
            });
        });
    </script>
</body>
</html>
