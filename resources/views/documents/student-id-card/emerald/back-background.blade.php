<svg xmlns="http://www.w3.org/2000/svg" width="{{ $width }}mm" height="{{ $height }}mm" viewBox="0 0 540 856">
    <defs>
        <linearGradient id="brand" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#064e3b" />
            <stop offset="0.55" stop-color="#047857" />
            <stop offset="1" stop-color="#10b981" />
        </linearGradient>
        <linearGradient id="lime" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0" stop-color="#d9f99d" />
            <stop offset="1" stop-color="#84cc16" />
        </linearGradient>
    </defs>

    <rect width="540" height="856" fill="#ffffff" />

    <polygon points="540,300 540,560 440,430" fill="#f0fdf4" />
    <polygon points="0,560 0,770 90,665" fill="#ecfdf5" />

    {{-- Header --}}
    <polygon points="0,0 540,0 540,112 270,150 0,112" fill="url(#lime)" />
    <polygon points="0,0 540,0 540,98 270,136 0,98" fill="url(#brand)" />
    <polygon points="540,0 540,98 420,0" fill="#ffffff" fill-opacity="0.07" />
    <polygon points="0,0 0,98 120,0" fill="#ffffff" fill-opacity="0.06" />

    {{-- Footer --}}
    <polygon points="0,800 330,764 540,788 540,856 0,856" fill="url(#lime)" />
    <polygon points="0,814 330,778 540,802 540,856 0,856" fill="url(#brand)" />

    {{-- Cut guide --}}
    <rect x="1" y="1" width="538" height="854" fill="none" stroke="#cbd5e1" stroke-width="2" />
</svg>
