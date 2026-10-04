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

    {{-- Mint facets behind the details --}}
    <polygon points="0,540 0,760 96,650" fill="#ecfdf5" />
    <polygon points="540,430 540,700 430,565" fill="#f0fdf4" />
    <polygon points="540,700 540,770 500,735" fill="#d1fae5" />

    {{-- Header: a chevron pointing down behind the portrait, edged in lime --}}
    <polygon points="0,0 540,0 540,262 270,356 0,262" fill="url(#lime)" />
    <polygon points="0,0 540,0 540,246 270,340 0,246" fill="url(#brand)" />
    <polygon points="540,0 540,170 370,0" fill="#ffffff" fill-opacity="0.07" />
    <polygon points="0,0 0,150 150,0" fill="#ffffff" fill-opacity="0.06" />
    <polygon points="0,246 0,180 190,246 95,279" fill="#ffffff" fill-opacity="0.05" />
    <polygon points="540,246 540,180 350,246 445,279" fill="#ffffff" fill-opacity="0.05" />

    {{-- Footer --}}
    <polygon points="0,788 210,764 540,800 540,856 0,856" fill="url(#lime)" />
    <polygon points="0,802 210,778 540,814 540,856 0,856" fill="url(#brand)" />

    {{-- Cut guide --}}
    <rect x="1" y="1" width="538" height="854" fill="none" stroke="#cbd5e1" stroke-width="2" />
</svg>
