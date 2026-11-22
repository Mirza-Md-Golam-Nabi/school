<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#060818">
    <meta name="description" content="{{ $schoolName }} — স্মার্ট স্কুল ম্যানেজমেন্ট সিস্টেম">
    <title>{{ $schoolName }} · স্মার্ট ক্যাম্পাস</title>
    <script>
        (() => {
            let stored = null;
            try { stored = localStorage.getItem('home-theme'); } catch (e) {}
            const prefersLight = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches;
            const theme = stored === 'light' || stored === 'dark' ? stored : (prefersLight ? 'light' : 'dark');
            document.documentElement.dataset.theme = theme;
            document.querySelector('meta[name="theme-color"]').content = theme === 'light' ? '#f8fafc' : '#060818';
        })();
    </script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=hind-siliguri:400,500,600,700|baloo-da-2:600,700,800|space-grotesk:500,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #060818;
            --bg-2: #10123a;
            --ink: #f1f5f9;
            --muted: #94a3b8;
            --line: rgb(255 255 255 / 0.09);
            --glass: rgb(255 255 255 / 0.045);
            --gold: #fbbf24;
            --indigo: #818cf8;
            --cyan: #22d3ee;
            --pink: #f472b6;
            --emerald: #34d399;
            --font: 'Hind Siliguri', 'Nirmala UI', 'Vrinda', system-ui, sans-serif;
            --display: 'Baloo Da 2', 'Hind Siliguri', 'Nirmala UI', system-ui, sans-serif;
            --latin: 'Space Grotesk', 'Hind Siliguri', system-ui, sans-serif;
            --nav-bg: rgb(6 8 24 / .55);
            --surface: rgb(10 12 36 / .85);
            --card-from: rgb(255 255 255 / .07);
            --card-to: rgb(255 255 255 / .02);
            --glass-hover: rgb(255 255 255 / .1);
            --soft: #cbd5e1;
            --strong: #fff;
            --footer-strong: #e2e8f0;
            --meta: #64748b;
            --mix: #fff;
            --band-border: rgb(255 255 255 / .12);
            --marquee-bg: rgb(255 255 255 / .02);
            --drop: rgb(0 0 0 / .45);
            --title-edge: #fff;
            --title-mid: #c7d2fe;
            --title-end: #f9a8d4;
            --orb-opacity: 1;
            color-scheme: dark;
        }

        :root[data-theme="light"] {
            --bg: #f8fafc;
            --bg-2: #e0e7ff;
            --ink: #0f172a;
            --muted: #475569;
            --line: rgb(15 23 42 / 0.1);
            --glass: rgb(15 23 42 / 0.04);
            --gold: #d97706;
            --indigo: #4f46e5;
            --cyan: #0891b2;
            --pink: #db2777;
            --emerald: #059669;
            --nav-bg: rgb(248 250 252 / .75);
            --surface: rgb(255 255 255 / .85);
            --card-from: rgb(255 255 255 / .95);
            --card-to: rgb(255 255 255 / .65);
            --glass-hover: rgb(15 23 42 / .07);
            --soft: #334155;
            --strong: #0f172a;
            --footer-strong: #0f172a;
            --meta: #64748b;
            --mix: #0f172a;
            --band-border: rgb(15 23 42 / .08);
            --marquee-bg: rgb(255 255 255 / .55);
            --drop: rgb(15 23 42 / .18);
            --title-edge: #1e1b4b;
            --title-mid: #4338ca;
            --title-end: #db2777;
            --orb-opacity: .45;
            color-scheme: light;
        }
        :root[data-theme="light"] .star { display: none; }

        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0; min-height: 100vh; overflow-x: hidden;
            font-family: var(--font); color: var(--ink); background: var(--bg);
            -webkit-font-smoothing: antialiased; font-size: .8rem;
        }
        @media (min-width: 640px) { body { font-size: 1rem; } }
        a { color: inherit; text-decoration: none; }
        svg { display: block; }

        /* ---------- Animated backdrop ---------- */
        .sky { position: fixed; inset: 0; z-index: -1; overflow: hidden; background: radial-gradient(ellipse at top, var(--bg-2), var(--bg) 65%); }
        .sky::after {
            content: ''; position: absolute; inset: 0;
            background-image: linear-gradient(var(--line) 1px, transparent 1px), linear-gradient(90deg, var(--line) 1px, transparent 1px);
            background-size: 64px 64px;
            mask-image: radial-gradient(ellipse at 50% 20%, #000 10%, transparent 70%);
            -webkit-mask-image: radial-gradient(ellipse at 50% 20%, #000 10%, transparent 70%);
            opacity: .5;
        }
        .orb { position: absolute; border-radius: 50%; filter: blur(90px); opacity: calc(.55 * var(--orb-opacity)); animation: drift 22s ease-in-out infinite alternate; }
        .orb--indigo { width: 46vw; height: 46vw; background: #4338ca; top: -14vw; left: -10vw; }
        .orb--gold { width: 32vw; height: 32vw; background: #b45309; top: 30vh; right: -10vw; animation-delay: -6s; opacity: calc(.35 * var(--orb-opacity)); }
        .orb--pink { width: 38vw; height: 38vw; background: #be185d; bottom: -16vw; left: 30vw; animation-delay: -12s; opacity: calc(.3 * var(--orb-opacity)); }
        @keyframes drift { to { transform: translate3d(6vw, 4vh, 0) scale(1.15); } }
        .star { position: absolute; width: 2px; height: 2px; border-radius: 50%; background: #fff; animation: twinkle 3s ease-in-out infinite; }
        @keyframes twinkle { 50% { opacity: .15; } }

        .wrap { width: min(1200px, 100% - 32px); margin-inline: auto; }

        /* ---------- Nav ---------- */
        .nav { position: sticky; top: 0; z-index: 20; backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); background: var(--nav-bg); border-bottom: 1px solid var(--line); }
        .nav__inner { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .75rem 0; }
        .brand { display: flex; align-items: center; gap: .7rem; min-width: 0; }
        .brand__mark {
            flex: none; display: grid; place-items: center; width: 2.4rem; height: 2.4rem; border-radius: .9rem; color: #fff; overflow: hidden;
            background: linear-gradient(135deg, var(--indigo), #4f46e5 55%, #312e81);
            box-shadow: 0 0 0 1px rgb(255 255 255 / .15) inset, 0 10px 30px -8px rgb(129 140 248 / .7);
        }
        .brand__mark img { width: 100%; height: 100%; object-fit: cover; background: #fff; }
        .brand__mark svg { width: 1.35rem; height: 1.35rem; }
        .brand__title { display: block; font-weight: 700; line-height: 1.5; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .brand__sub { display: block; font-size: .7rem; color: var(--muted); font-family: var(--latin); letter-spacing: .04em; }
        .nav__links { display: none; gap: 1.6rem; font-size: .92rem; color: var(--muted); }
        .nav__links a:hover { color: var(--strong); }
        @media (min-width: 900px) { .nav__links { display: flex; } }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: .5rem; padding: .65rem 1.15rem; border-radius: 999px;
            font-weight: 600; font-size: .8rem; transition: transform .2s, box-shadow .2s, background .2s; white-space: nowrap;
        }
        @media (min-width: 640px) { .btn { padding: .75rem 1.35rem; font-size: .95rem; } }
        .btn svg { width: 1.1rem; height: 1.1rem; }
        .btn--primary { color: #1c1300; background: linear-gradient(135deg, #fde68a, var(--gold) 45%, #f59e0b); box-shadow: 0 10px 30px -10px rgb(251 191 36 / .8); }
        .btn--primary:hover { transform: translateY(-2px); box-shadow: 0 16px 40px -10px rgb(251 191 36 / .95); }
        .btn--ghost { background: var(--glass); border: 1px solid var(--line); }
        .btn--ghost:hover { background: var(--glass-hover); transform: translateY(-2px); }
        .btn--sm { padding: .5rem .95rem; font-size: .78rem; }
        .nav__actions { display: flex; align-items: center; gap: .5rem; flex: none; }
        .theme-toggle { display: grid; place-items: center; width: 2.25rem; height: 2.25rem; padding: 0; border-radius: 50%; color: var(--ink); background: var(--glass); border: 1px solid var(--line); cursor: pointer; transition: transform .2s, background .2s; }
        .theme-toggle:hover { background: var(--glass-hover); transform: rotate(-12deg); }
        .theme-toggle svg { width: 1.05rem; height: 1.05rem; }
        .theme-toggle .icon-sun { display: none; }
        :root[data-theme="light"] .theme-toggle .icon-sun { display: block; }
        :root[data-theme="light"] .theme-toggle .icon-moon { display: none; }

        /* ---------- Hero ---------- */
        .hero { padding: clamp(2.5rem, 8vw, 5.5rem) 0 1rem; text-align: center; }
        .pill {
            display: inline-flex; align-items: center; gap: .55rem; padding: .35rem .9rem .35rem .45rem; border-radius: 999px;
            background: var(--glass); border: 1px solid var(--line); font-size: .78rem; color: var(--soft);
        }
        .pill__dot { display: inline-flex; align-items: center; padding: .12rem .55rem; border-radius: 999px; background: rgb(52 211 153 / .15); color: var(--emerald); font-family: var(--latin); font-weight: 700; font-size: .68rem; letter-spacing: .06em; }
        .pill__dot::before { content: ''; width: .45rem; height: .45rem; margin-right: .35rem; border-radius: 50%; background: var(--emerald); animation: ping 1.8s infinite; }
        @keyframes ping { 0% { box-shadow: 0 0 0 0 rgb(52 211 153 / .7); } 70%, 100% { box-shadow: 0 0 0 .55rem rgb(52 211 153 / 0); } }

        .hero__title {
            margin: 1.2rem auto .4rem; max-width: 17ch; font-family: var(--display); font-weight: 800;
            font-size: clamp(2rem, 7vw, 5rem); line-height: 1.35; letter-spacing: -.01em;
            background: linear-gradient(100deg, var(--title-edge) 10%, var(--title-mid) 30%, var(--gold) 55%, var(--title-end) 75%, var(--title-edge) 90%);
            background-size: 220% auto; -webkit-background-clip: text; background-clip: text; color: transparent;
            animation: shine 7s linear infinite;
        }
        @keyframes shine { to { background-position: -220% center; } }
        .hero__lead { margin: 0 auto; max-width: 50ch; color: var(--muted); font-size: clamp(.85rem, 2.2vw, 1.15rem); line-height: 1.75; }
        .hero__meta { margin-top: .8rem; font-size: .78rem; color: var(--meta); font-family: var(--latin); }
        .hero__cta { display: flex; flex-wrap: wrap; justify-content: center; gap: .6rem; margin-top: 1.8rem; }

        .stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1px; max-width: 860px; margin: 2.5rem auto 0; border-radius: 1.25rem; overflow: hidden; background: var(--line); border: 1px solid var(--line); }
        @media (min-width: 640px) { .stats { grid-template-columns: repeat(4, 1fr); } }
        .stat { padding: 1rem .75rem; background: var(--surface); }
        .stat__value { font-family: var(--display); font-weight: 800; font-size: clamp(1.35rem, 4vw, 2.1rem); line-height: 1.15; }
        .stat__label { margin-top: .2rem; font-size: .72rem; color: var(--muted); }
        @media (min-width: 640px) { .stat__label { font-size: .82rem; } }
        .stat:nth-child(1) .stat__value { color: var(--gold); }
        .stat:nth-child(2) .stat__value { color: var(--indigo); }
        .stat:nth-child(3) .stat__value { color: var(--pink); }
        .stat:nth-child(4) .stat__value { color: var(--emerald); }

        /* ---------- Campus scene ---------- */
        .scene { position: relative; margin: 2.5rem auto 0; max-width: 1000px; height: clamp(170px, 26vw, 280px); }
        .scene svg.campus { position: absolute; inset: 0; width: 100%; height: 100%; }
        .campus .win { fill: var(--gold); animation: blink 3.2s ease-in-out infinite; }
        .campus .win:nth-of-type(3n) { animation-delay: -1.1s; }
        .campus .win:nth-of-type(3n+1) { animation-delay: -2.2s; }
        @keyframes blink { 50% { opacity: .35; } }
        
        .floaty { position: absolute; font-size: clamp(1.1rem, 2.6vw, 1.9rem); filter: drop-shadow(0 8px 12px var(--drop)); animation: float 6s ease-in-out infinite; }
        @keyframes float { 50% { transform: translateY(-14px) rotate(6deg); } }
        .ground { position: absolute; left: 0; right: 0; bottom: 0; height: 14%; background: linear-gradient(180deg, rgb(129 140 248 / .16), transparent); mask-image: linear-gradient(90deg, transparent, #000 15%, #000 85%, transparent); -webkit-mask-image: linear-gradient(90deg, transparent, #000 15%, #000 85%, transparent); }

        /* ---------- Sections ---------- */
        .section { padding: clamp(2.5rem, 7vw, 5rem) 0; }
        .section__head { margin-bottom: 1.8rem; text-align: center; }
        .eyebrow { font-family: var(--latin); text-transform: uppercase; letter-spacing: .18em; font-size: .68rem; font-weight: 700; color: var(--gold); }
        .section__title { margin: .35rem 0 .4rem; font-family: var(--display); font-weight: 800; font-size: clamp(1.5rem, 4.5vw, 2.9rem); line-height: 1.25; }
        .section__note { margin: 0 auto; max-width: 52ch; color: var(--muted); line-height: 1.7; }

        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1rem; }
        .grid--portals { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
        .card {
            --c: #818cf8;
            position: relative; isolation: isolate; overflow: hidden; display: flex; flex-direction: column;
            padding: 1.25rem 1.25rem 1.15rem; border-radius: 1.4rem;
            background: linear-gradient(160deg, var(--card-from), var(--card-to));
            border: 1px solid var(--line);
            transition: transform .35s cubic-bezier(.2, .8, .2, 1), border-color .35s, box-shadow .35s;
        }
        .reveal { opacity: 0; transform: translateY(24px); }
        .reveal.is-visible { opacity: 1; transform: none; transition: opacity .6s ease, transform .6s cubic-bezier(.2, .8, .2, 1), border-color .35s, box-shadow .35s; }
        .card::before {
            content: ''; position: absolute; z-index: -1; width: 180px; height: 180px; right: -60px; top: -70px; border-radius: 50%;
            background: var(--c); filter: blur(50px); opacity: .35; transition: opacity .35s, transform .35s;
        }
        .card:hover { transform: translateY(-6px); border-color: color-mix(in srgb, var(--c) 60%, transparent); box-shadow: 0 24px 50px -20px color-mix(in srgb, var(--c) 70%, transparent); }
        .card:hover::before { opacity: .6; transform: scale(1.25); }
        .card__top { display: flex; align-items: flex-start; justify-content: space-between; }
        .card__badge {
            display: grid; place-items: center; min-width: 2.2rem; height: 2.2rem; padding: 0 .55rem; border-radius: .75rem;
            font-family: var(--latin); font-weight: 700; font-size: .72rem; letter-spacing: .06em; color: var(--ink);
            background: color-mix(in srgb, var(--c) 30%, transparent); border: 1px solid color-mix(in srgb, var(--c) 55%, transparent);
        }
        .card__icon { font-size: 2.5rem; line-height: 1; filter: drop-shadow(0 8px 14px var(--drop)); transition: transform .4s; }
        .card:hover .card__icon { transform: translateX(4px) rotate(-6deg) scale(1.1); }
        .card__name { margin: .9rem 0 .15rem; font-size: 1.05rem; font-weight: 700; line-height: 1.35; }
        @media (min-width: 640px) { .card__name { font-size: 1.2rem; } }
        .card__alt { font-size: .75rem; color: color-mix(in srgb, var(--c) 55%, var(--mix)); font-family: var(--latin); letter-spacing: .03em; }
        .card__text { margin: .55rem 0 0; color: var(--muted); line-height: 1.65; font-size: .78rem; }
        @media (min-width: 640px) { .card__text { font-size: .9rem; } }
        .card__list { margin: .9rem 0 0; padding: 0; list-style: none; display: grid; gap: .4rem; font-size: .78rem; color: var(--soft); }
        @media (min-width: 640px) { .card__list { font-size: .88rem; } }
        .card__list li { display: flex; gap: .5rem; align-items: center; }
        .card__list li::before { content: ''; flex: none; width: .4rem; height: .4rem; border-radius: 50%; background: var(--c); box-shadow: 0 0 10px var(--c); }
        .card__go {
            margin-top: auto; padding-top: 1.2rem; display: flex; align-items: center; justify-content: space-between;
            font-weight: 600; font-size: .85rem; color: color-mix(in srgb, var(--c) 45%, var(--mix));
        }
        .card__go span.arrow { display: grid; place-items: center; width: 2.1rem; height: 2.1rem; border-radius: 50%; background: color-mix(in srgb, var(--c) 25%, transparent); border: 1px solid color-mix(in srgb, var(--c) 50%, transparent); transition: transform .3s; }
        .card:hover .card__go span.arrow { transform: translateX(4px); }
        .card__go svg { width: 1rem; height: 1rem; }

        .card--portal { padding: 1.5rem; min-height: 100%; }
        .card--portal .card__icon { font-size: 3rem; }

        /* ---------- Features (compact) ---------- */
        .feature { --c: #818cf8; padding: 1.1rem; }
        .feature .card__icon { font-size: 1.9rem; }
        .feature .card__name { margin-top: .7rem; font-size: 1rem; }

        /* ---------- Marquee ---------- */
        .marquee { overflow: hidden; border-block: 1px solid var(--line); background: var(--marquee-bg); mask-image: linear-gradient(90deg, transparent, #000 10%, #000 90%, transparent); -webkit-mask-image: linear-gradient(90deg, transparent, #000 10%, #000 90%, transparent); }
        .marquee__track { display: flex; width: max-content; gap: 2.5rem; padding: .9rem 0; animation: scroll 40s linear infinite; font-family: var(--latin); font-size: .78rem; color: var(--muted); letter-spacing: .08em; text-transform: uppercase; }
        .marquee__track span { display: inline-flex; align-items: center; gap: .6rem; white-space: nowrap; }
        .marquee__track span::before { content: '✦'; color: var(--gold); }
        @keyframes scroll { to { transform: translateX(-50%); } }

        /* ---------- CTA band ---------- */
        .band {
            position: relative; overflow: hidden; isolation: isolate; text-align: center; padding: clamp(2rem, 6vw, 3.5rem) 1.25rem; border-radius: 1.75rem;
            background: linear-gradient(135deg, rgb(79 70 229 / .35), rgb(190 24 93 / .22) 55%, rgb(180 83 9 / .28));
            border: 1px solid var(--band-border);
        }
        .band::after { content: ''; position: absolute; inset: 0; z-index: -1; background: radial-gradient(circle at 20% 0%, rgb(251 191 36 / .25), transparent 45%), radial-gradient(circle at 90% 100%, rgb(129 140 248 / .35), transparent 50%); }

        /* ---------- Footer ---------- */
        .footer { padding: 2rem 0 2.5rem; border-top: 1px solid var(--line); color: var(--muted); font-size: .75rem; }
        .footer__inner { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; }
        .footer strong { color: var(--footer-strong); }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
            .reveal { opacity: 1; transform: none; }
        }
    </style>
</head>
<body>
    @php
        $panelUrl = fn (string $panel): string => \Filament\Facades\Filament::getPanel($panel)->getUrl();

        $portals = [
            [
                'badge' => 'ADMIN', 'icon' => '🛡️', 'color' => '#fbbf24',
                'name' => 'অ্যাডমিন প্যানেল', 'alt' => 'Admin Panel',
                'text' => 'স্কুলের সম্পূর্ণ ব্যবস্থাপনা ও নিয়ন্ত্রণ — এক ড্যাশবোর্ডে সবকিছু।',
                'items' => ['শিক্ষার্থী, শিক্ষক ও স্টাফ', 'ফি, হিসাব ও বেতন', 'পরীক্ষা, রেজাল্ট ও ডকুমেন্ট'],
                'url' => $panelUrl('admin'),
            ],
            [
                'badge' => 'TEACHER', 'icon' => '👩‍🏫', 'color' => '#818cf8',
                'name' => 'শিক্ষক প্যানেল', 'alt' => 'Teacher Panel',
                'text' => 'নিজের ক্লাস, মার্কস এন্ট্রি ও উপস্থিতি — দ্রুত ও সহজে।',
                'items' => ['ক্লাসভিত্তিক মার্কস এন্ট্রি', 'দৈনিক উপস্থিতি', 'ছুটির আবেদন ও নোটিশ'],
                'url' => $panelUrl('teacher'),
            ],
            [
                'badge' => 'STUDENT', 'icon' => '🎓', 'color' => '#f472b6',
                'name' => 'শিক্ষার্থী প্যানেল', 'alt' => 'Student Panel',
                'text' => 'রেজাল্ট, উপস্থিতি ও নোটিশ — যেকোনো সময়, যেকোনো জায়গা থেকে।',
                'items' => ['মার্কশিট ও মেধাক্রম', 'উপস্থিতির রিপোর্ট', 'ফি ইনভয়েস ও নোটিশ'],
                'url' => $panelUrl('student'),
            ],
        ];

        $features = [
            ['icon' => '🕒', 'color' => '#34d399', 'name' => 'স্মার্ট উপস্থিতি', 'alt' => 'Attendance', 'text' => 'বায়োমেট্রিক ডিভাইস সংযোগ, লেট পেনাল্টি ও অভিভাবককে স্বয়ংক্রিয় নোটিফিকেশন।'],
            ['icon' => '📊', 'color' => '#818cf8', 'name' => 'পরীক্ষা ও রেজাল্ট', 'alt' => 'Exams & Results', 'text' => 'GPA গ্রেডিং, সাবজেক্ট মার্জ, টেবুলেশন শিট ও মেধাক্রম — এক ক্লিকে।'],
            ['icon' => '💳', 'color' => '#fbbf24', 'name' => 'ফি ও হিসাব', 'alt' => 'Fees & Finance', 'text' => 'ইনভয়েস, ছাড়, লেট ফি, পেমেন্ট স্লিপ ও স্কুলের আয়-ব্যয়ের পূর্ণ হিসাব।'],
            ['icon' => '📄', 'color' => '#f472b6', 'name' => 'ডকুমেন্ট জেনারেশন', 'alt' => 'Documents', 'text' => 'অ্যাডমিট কার্ড, মার্কশিট, আইডি কার্ড, সিট প্ল্যান ও টিসি — PDF-এ প্রস্তুত।'],
            ['icon' => '📢', 'color' => '#22d3ee', 'name' => 'নোটিশ ও SMS', 'alt' => 'Communication', 'text' => 'নির্দিষ্ট ক্লাস বা গ্রুপে নোটিশ, পুশ নোটিফিকেশন ও SMS পাঠানো।'],
            ['icon' => '💼', 'color' => '#a78bfa', 'name' => 'বেতন ও HR', 'alt' => 'Salary & Leave', 'text' => 'বেতন কাঠামো, বাল্ক পেমেন্ট, কর্তন ও ছুটির আবেদন ব্যবস্থাপনা।'],
            ['icon' => '🚀', 'color' => '#fb923c', 'name' => 'প্রমোশন', 'alt' => 'Promotion', 'text' => 'রেজাল্টের ভিত্তিতে পরবর্তী ক্লাসে বাল্ক প্রমোশন ও ক্লাস ইতিহাস।'],
            ['icon' => '📅', 'color' => '#2dd4bf', 'name' => 'ইভেন্ট ক্যালেন্ডার', 'alt' => 'Events', 'text' => 'ছুটি, পরীক্ষা ও অনুষ্ঠানের সময়সূচি — সবার জন্য এক ক্যালেন্ডারে।'],
        ];
    @endphp

    <div class="sky" aria-hidden="true">
        <span class="orb orb--indigo"></span>
        <span class="orb orb--gold"></span>
        <span class="orb orb--pink"></span>
        @foreach ([[8, 12], [18, 30], [27, 8], [42, 22], [55, 6], [63, 34], [72, 14], [84, 26], [92, 9], [12, 46], [50, 40], [88, 44]] as [$x, $y])
            <span class="star" style="left: {{ $x }}%; top: {{ $y }}%; animation-delay: -{{ $loop->index * 0.4 }}s"></span>
        @endforeach
    </div>

    {{-- ---------- Nav ---------- --}}
    <header class="nav">
        <div class="wrap nav__inner">
            <a href="{{ route('home') }}" class="brand">
                <span class="brand__mark">
                    @if ($schoolLogoUrl)
                        <img src="{{ $schoolLogoUrl }}" alt="{{ $schoolName }}">
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.5 2.7 3 6 3s6-1.5 6-3v-5"/><path d="M22 10v6"/></svg>
                    @endif
                </span>
                <span style="min-width: 0">
                    <span class="brand__title">{{ $schoolName }}</span>
                    <span class="brand__sub">SMART CAMPUS</span>
                </span>
            </a>
            <nav class="nav__links">
                <a href="#portals">পোর্টাল</a>
                <a href="#features">ফিচার</a>
                <a href="#contact">যোগাযোগ</a>
            </nav>
            <div class="nav__actions">
                <button type="button" class="theme-toggle" id="theme-toggle" aria-label="থিম পরিবর্তন করুন" title="লাইট / ডার্ক থিম">
                    <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
                    <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
                </button>
                <a href="#portals" class="btn btn--primary btn--sm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/></svg>
                    লগইন
                </a>
            </div>
        </div>
    </header>

    <main>
        {{-- ---------- Hero ---------- --}}
        <section class="hero">
            <div class="wrap">
                <span class="pill"><span class="pill__dot">LIVE</span> ডিজিটাল ক্যাম্পাস · সারা বছর ২৪/৭</span>
                <h1 class="hero__title">{{ $schoolName }}</h1>
                <p class="hero__lead">শিক্ষার্থী, শিক্ষক ও অভিভাবকদের জন্য একটি আধুনিক প্ল্যাটফর্ম — উপস্থিতি, পরীক্ষা, রেজাল্ট, ফি আর নোটিশ, সবকিছু এক জায়গায়।</p>
                @if ($schoolAddress || $eiinLine || $establishedYear)
                    <p class="hero__meta">
                        {{ collect([$schoolAddress, $eiinLine, $establishedYear ? 'Estd. '.$establishedYear : null])->filter()->implode(' · ') }}
                    </p>
                @endif
                <div class="hero__cta">
                    <a href="#portals" class="btn btn--primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                        পোর্টালে প্রবেশ করুন
                    </a>
                    <a href="#features" class="btn btn--ghost">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 14.5 9.5 21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5Z"/></svg>
                        ফিচারগুলো দেখুন
                    </a>
                </div>

                <div class="stats">
                    <div class="stat"><div class="stat__value">১১+</div><div class="stat__label">ম্যানেজমেন্ট মডিউল</div></div>
                    <div class="stat"><div class="stat__value">৩</div><div class="stat__label">আলাদা পোর্টাল</div></div>
                    <div class="stat"><div class="stat__value">PDF</div><div class="stat__label">ইনস্ট্যান্ট ডকুমেন্ট</div></div>
                    <div class="stat"><div class="stat__value">২৪/৭</div><div class="stat__label">অনলাইন অ্যাক্সেস</div></div>
                </div>

                {{-- ---------- Campus scene ---------- --}}
                <div class="scene" aria-hidden="true">
                    <svg class="campus" viewBox="0 0 1000 280" preserveAspectRatio="xMidYMax meet">
                        <defs>
                            <linearGradient id="wallGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0" stop-color="#312e81"/>
                                <stop offset="1" stop-color="#151643"/>
                            </linearGradient>
                            <linearGradient id="roofGrad" x1="0" y1="0" x2="1" y2="0">
                                <stop offset="0" stop-color="#fbbf24"/>
                                <stop offset=".5" stop-color="#f472b6"/>
                                <stop offset="1" stop-color="#818cf8"/>
                            </linearGradient>
                            <linearGradient id="wingGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0" stop-color="#1e1b4b"/>
                                <stop offset="1" stop-color="#0f1033"/>
                            </linearGradient>
                        </defs>

                        {{-- Side wings --}}
                        <rect x="150" y="140" width="240" height="130" rx="6" fill="url(#wingGrad)" stroke="rgb(255 255 255 / .08)"/>
                        <rect x="610" y="140" width="240" height="130" rx="6" fill="url(#wingGrad)" stroke="rgb(255 255 255 / .08)"/>
                        @foreach ([175, 225, 275, 325] as $x)
                            <rect class="win" x="{{ $x }}" y="165" width="28" height="22" rx="3"/>
                            <rect class="win" x="{{ $x }}" y="210" width="28" height="22" rx="3" opacity=".7"/>
                            <rect class="win" x="{{ $x + 460 }}" y="165" width="28" height="22" rx="3" opacity=".8"/>
                            <rect class="win" x="{{ $x + 460 }}" y="210" width="28" height="22" rx="3"/>
                        @endforeach

                        {{-- Main hall --}}
                        <rect x="380" y="95" width="240" height="175" rx="6" fill="url(#wallGrad)" stroke="rgb(255 255 255 / .12)"/>
                        <path d="M365 100 500 30 635 100Z" fill="url(#roofGrad)" opacity=".9"/>
                        <circle cx="500" cy="72" r="14" fill="#060818" stroke="#fde68a" stroke-width="2"/>
                        <path d="M500 64v8l5 4" stroke="#fde68a" stroke-width="2" fill="none" stroke-linecap="round"/>
                        @foreach ([410, 455, 520, 565] as $x)
                            <rect x="{{ $x }}" y="120" width="10" height="150" fill="rgb(255 255 255 / .08)"/>
                        @endforeach
                        <rect class="win" x="425" y="130" width="24" height="30" rx="12"/>
                        <rect class="win" x="551" y="130" width="24" height="30" rx="12"/>
                        <path d="M470 270v-60a30 30 0 0 1 60 0v60Z" fill="#0b0c26" stroke="#fbbf24" stroke-opacity=".6" stroke-width="2"/>
                        <rect x="360" y="262" width="280" height="8" rx="2" fill="rgb(255 255 255 / .1)"/>

                        {{-- Flag --}}
                        <line x1="482" y1="39" x2="482" y2="0" stroke="#94a3b8" stroke-width="2"/>
                        <g class="flag" transform="translate(483 0)">
                            <rect width="34" height="20" fill="#006a4e"/>
                            <circle cx="15" cy="10" r="6" fill="#f42a41"/>
                        </g>

                        {{-- Trees --}}
                        @foreach ([[95, 1], [905, 1.1], [60, .8], [945, .75]] as [$tx, $ts])
                            <g transform="translate({{ $tx }} 270) scale({{ $ts }})">
                                <rect x="-4" y="-40" width="8" height="40" fill="#3f2a1d"/>
                                <circle cx="0" cy="-62" r="30" fill="#065f46" opacity=".9"/>
                                <circle cx="-14" cy="-50" r="20" fill="#047857" opacity=".9"/>
                                <circle cx="14" cy="-48" r="18" fill="#10b981" opacity=".55"/>
                            </g>
                        @endforeach
                        <line x1="0" y1="270" x2="1000" y2="270" style="stroke: var(--line)"/>
                    </svg>
                    <span class="floaty" style="left: 6%; top: 8%">📚</span>
                    <span class="floaty" style="left: 22%; top: -4%; animation-delay: -2s">✏️</span>
                    <span class="floaty" style="right: 22%; top: -2%; animation-delay: -4s">🎓</span>
                    <span class="floaty" style="right: 6%; top: 12%; animation-delay: -1s">🏆</span>
                    <div class="ground"></div>
                </div>
            </div>
        </section>

        {{-- ---------- Marquee ---------- --}}
        <div class="marquee" aria-hidden="true">
            <div class="marquee__track">
                @foreach (range(1, 2) as $_)
                    @foreach (['Attendance', 'Exams & Results', 'Marksheets', 'Admit Cards', 'Fee Collection', 'Salary', 'Notices', 'SMS', 'ID Cards', 'Promotion', 'Events', 'Leave Management'] as $module)
                        <span>{{ $module }}</span>
                    @endforeach
                @endforeach
            </div>
        </div>

        {{-- ---------- Portals ---------- --}}
        <section class="section" id="portals">
            <div class="wrap">
                <div class="section__head">
                    <div class="eyebrow">Choose Your Portal</div>
                    <h2 class="section__title">আপনার পোর্টাল বেছে নিন</h2>
                    <p class="section__note">প্রতিটি ব্যবহারকারীর জন্য আলাদা, নিরাপদ ও রোল-ভিত্তিক অ্যাক্সেস।</p>
                </div>
                <div class="grid grid--portals">
                    @foreach ($portals as $portal)
                        <a href="{{ $portal['url'] }}" class="card card--portal reveal" style="--c: {{ $portal['color'] }}">
                            <div class="card__top">
                                <span class="card__badge">{{ $portal['badge'] }}</span>
                                <span class="card__icon">{{ $portal['icon'] }}</span>
                            </div>
                            <h3 class="card__name">{{ $portal['name'] }}</h3>
                            <div class="card__alt">{{ $portal['alt'] }}</div>
                            <p class="card__text">{{ $portal['text'] }}</p>
                            <ul class="card__list">
                                @foreach ($portal['items'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                            <div class="card__go">
                                প্রবেশ করুন
                                <span class="arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg></span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ---------- Features ---------- --}}
        <section class="section" id="features" style="padding-top: 0">
            <div class="wrap">
                <div class="section__head">
                    <div class="eyebrow">Everything In One Place</div>
                    <h2 class="section__title">একটি সিস্টেম, সব সমাধান</h2>
                    <p class="section__note">কাগজের ঝামেলা ছাড়াই পুরো স্কুল পরিচালনা করুন — দ্রুত, নির্ভুল ও স্বচ্ছভাবে।</p>
                </div>
                <div class="grid">
                    @foreach ($features as $feature)
                        <div class="card feature reveal" style="--c: {{ $feature['color'] }}">
                            <span class="card__icon">{{ $feature['icon'] }}</span>
                            <h3 class="card__name">{{ $feature['name'] }}</h3>
                            <div class="card__alt">{{ $feature['alt'] }}</div>
                            <p class="card__text">{{ $feature['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ---------- CTA band ---------- --}}
        <section class="section" id="contact" style="padding-top: 0">
            <div class="wrap">
                <div class="band reveal">
                    <div class="eyebrow">Get Started</div>
                    <h2 class="section__title">শিক্ষাকে করুন আরও স্মার্ট</h2>
                    <p class="section__note">
                        লগইন সংক্রান্ত সহায়তার জন্য স্কুল অফিসে যোগাযোগ করুন{{ $schoolAddress ? ' — '.$schoolAddress : '' }}।
                    </p>
                    <div class="hero__cta">
                        <a href="{{ $panelUrl('student') }}" class="btn btn--primary">🎓 শিক্ষার্থী লগইন</a>
                        <a href="{{ $panelUrl('teacher') }}" class="btn btn--ghost">👩‍🏫 শিক্ষক লগইন</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="wrap footer__inner">
            <span>© <span id="year">{{ now()->year }}</span> <strong>{{ $schoolName }}</strong> · সর্বস্বত্ব সংরক্ষিত</span>
            <span id="clock" style="font-family: var(--latin)"></span>
        </div>
    </footer>

    <script>
        (() => {
            const items = document.querySelectorAll('.reveal');
            if (!('IntersectionObserver' in window)) {
                items.forEach((el) => el.classList.add('is-visible'));
            } else {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.12 });
                items.forEach((el, i) => {
                    el.style.transitionDelay = `${(i % 4) * 70}ms`;
                    observer.observe(el);
                });
            }

            const root = document.documentElement;
            const themeMeta = document.querySelector('meta[name="theme-color"]');
            const applyTheme = (theme) => {
                root.dataset.theme = theme;
                themeMeta.content = theme === 'light' ? '#f8fafc' : '#060818';
            };
            const storedTheme = () => {
                try { return localStorage.getItem('home-theme'); } catch (e) { return null; }
            };

            document.getElementById('theme-toggle').addEventListener('click', () => {
                const next = root.dataset.theme === 'light' ? 'dark' : 'light';
                applyTheme(next);
                try { localStorage.setItem('home-theme', next); } catch (e) {}
            });

            if (window.matchMedia) {
                window.matchMedia('(prefers-color-scheme: light)').addEventListener('change', (event) => {
                    if (!storedTheme()) {
                        applyTheme(event.matches ? 'light' : 'dark');
                    }
                });
            }

            const clock = document.getElementById('clock');
            const tick = () => {
                clock.textContent = new Date().toLocaleString('bn-BD', { dateStyle: 'medium', timeStyle: 'medium' });
            };
            tick();
            setInterval(tick, 1000);
        })();
    </script>
</body>
</html>
