<svg xmlns="http://www.w3.org/2000/svg" width="{{ $width }}mm" height="{{ $height }}mm" viewBox="0 0 540 856">
    <defs>
        <linearGradient id="night" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#020617" />
            <stop offset="0.6" stop-color="#0f172a" />
            <stop offset="1" stop-color="#1e1b4b" />
        </linearGradient>
        <linearGradient id="gold" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0" stop-color="#fde68a" />
            <stop offset="0.5" stop-color="#f59e0b" />
            <stop offset="1" stop-color="#fcd34d" />
        </linearGradient>
    </defs>

    <rect width="540" height="856" fill="url(#night)" />

    {{-- Faint facets so the dark field is not flat --}}
    <polygon points="0,0 250,0 0,300" fill="#ffffff" fill-opacity="0.03" />
    <polygon points="540,330 540,790 300,790" fill="#ffffff" fill-opacity="0.03" />

    {{-- Gold halo rings around the portrait --}}
    <circle cx="270" cy="270" r="188" fill="#f59e0b" fill-opacity="0.07" />
    <circle cx="270" cy="270" r="160" fill="#f59e0b" fill-opacity="0.10" />
    <circle cx="270" cy="270" r="136" fill="#fcd34d" fill-opacity="0.16" />

    {{-- Hairline frame --}}
    <rect x="16" y="16" width="508" height="760" fill="none" stroke="#a16207" stroke-width="2" />
    <polygon points="16,16 76,16 16,76" fill="url(#gold)" />
    <polygon points="524,776 464,776 524,716" fill="url(#gold)" />

    {{-- Footer: a solid gold band carrying dark text --}}
    <polygon points="0,790 540,790 540,856 0,856" fill="url(#gold)" />
    <polygon points="0,790 540,790 540,796 0,796" fill="#fffbeb" fill-opacity="0.55" />

    {{-- Cut guide --}}
    <rect x="1" y="1" width="538" height="854" fill="none" stroke="#cbd5e1" stroke-width="2" />
</svg>
