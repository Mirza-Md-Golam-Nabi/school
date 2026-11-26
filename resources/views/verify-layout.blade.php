{{--
    The frame shared by the public "is this document real?" pages that the QR
    codes on admit cards and marksheets open: school name and logo on top, one
    card below. Not indexed by search engines.

    @var string $schoolName
    @var string|null $schoolLogoUrl
--}}
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · {{ $schoolName }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=hind-siliguri:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f6fb; --card: #fff; --ink: #1e1b4b; --muted: #64748b; --line: #e2e8f0; --soft: #f8fafc;
            --ok: #059669; --ok-bg: #ecfdf5; --warn: #b45309; --warn-bg: #fffbeb; --bad: #dc2626; --bad-bg: #fef2f2;
            --accent: #4f46e5;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0b1020; --card: #141a2e; --ink: #f1f5f9; --muted: #94a3b8; --line: #26304a; --soft: #10162a;
                --ok: #34d399; --ok-bg: rgb(52 211 153 / .12); --warn: #fbbf24; --warn-bg: rgb(251 191 36 / .12); --bad: #f87171; --bad-bg: rgb(248 113 113 / .12);
                --accent: #818cf8;
            }
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; background: var(--bg); color: var(--ink);
            font-family: 'Hind Siliguri', 'Nirmala UI', 'Vrinda', system-ui, sans-serif;
            font-size: .75rem; line-height: 1.6;
        }
        @media (min-width: 640px) { body { font-size: .95rem; } }
        .wrap { width: 100%; max-width: 520px; margin: 0 auto; padding: 16px; }
        .wrap--wide { max-width: 720px; }
        .school { display: flex; align-items: center; gap: .6rem; justify-content: center; margin: .5rem 0 1rem; font-weight: 700; text-align: center; }
        .school img { width: 2.4rem; height: 2.4rem; border-radius: .6rem; object-fit: cover; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 1rem; overflow: hidden; }
        .status { display: flex; align-items: center; gap: .6rem; padding: .9rem 1rem; font-weight: 700; font-size: 1.05em; }
        .status svg { flex: none; width: 1.6rem; height: 1.6rem; }
        .status--ok { background: var(--ok-bg); color: var(--ok); }
        .status--warn { background: var(--warn-bg); color: var(--warn); }
        .status--bad { background: var(--bad-bg); color: var(--bad); }
        .note { margin: .75rem 1rem 0; padding: .55rem .75rem; border-radius: .6rem; font-weight: 600; }
        .note--warn { background: var(--warn-bg); color: var(--warn); }
        .note--bad { background: var(--bad-bg); color: var(--bad); }
        .body { padding: 1rem; }
        .photo { display: block; width: 7rem; height: 8.5rem; margin: 0 auto 1rem; border-radius: .6rem; object-fit: cover; border: 1px solid var(--line); }
        .photo--empty { display: grid; place-items: center; color: var(--muted); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: .45rem .25rem; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
        tr:last-child th, tr:last-child td { border-bottom: 0; }
        .details th { width: 38%; color: var(--muted); font-weight: 500; }
        .details td { font-weight: 600; }
        .hint { margin-top: 1rem; color: var(--muted); text-align: center; font-size: .9em; }
    </style>
    @stack('styles')
</head>
<body>
    <main class="wrap @yield('wrapClass')">
        <div class="school">
            @if ($schoolLogoUrl)
                <img src="{{ $schoolLogoUrl }}" alt="">
            @endif
            <span>{{ $schoolName }}</span>
        </div>

        <div class="card">
            @yield('content')
        </div>
    </main>
</body>
</html>
