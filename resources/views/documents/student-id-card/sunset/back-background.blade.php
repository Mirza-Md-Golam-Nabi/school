<svg xmlns="http://www.w3.org/2000/svg" width="{{ $width }}mm" height="{{ $height }}mm" viewBox="0 0 540 856">
    <defs>
        <linearGradient id="brand" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#f97316" />
            <stop offset="0.5" stop-color="#e11d48" />
            <stop offset="1" stop-color="#9333ea" />
        </linearGradient>
        <linearGradient id="amber" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0" stop-color="#fde68a" />
            <stop offset="1" stop-color="#fbbf24" />
        </linearGradient>
    </defs>

    <rect width="540" height="856" fill="#ffffff" />

    <circle cx="570" cy="360" r="150" fill="#fff7ed" />
    <circle cx="-40" cy="640" r="130" fill="#fff1f2" />
    <circle cx="486" cy="560" r="20" fill="#ffe4e6" />

    {{-- Header --}}
    <circle cx="270" cy="-330" r="484" fill="url(#amber)" />
    <circle cx="270" cy="-330" r="470" fill="url(#brand)" />
    <circle cx="60" cy="10" r="70" fill="#ffffff" fill-opacity="0.07" />
    <circle cx="470" cy="20" r="54" fill="#ffffff" fill-opacity="0.07" />

    {{-- Footer --}}
    <circle cx="270" cy="1250" r="486" fill="url(#amber)" />
    <circle cx="270" cy="1250" r="472" fill="url(#brand)" />

    {{-- Cut guide --}}
    <rect x="1" y="1" width="538" height="854" fill="none" stroke="#cbd5e1" stroke-width="2" />
</svg>
