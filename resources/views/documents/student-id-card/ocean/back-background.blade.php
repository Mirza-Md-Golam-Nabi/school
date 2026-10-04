<svg xmlns="http://www.w3.org/2000/svg" width="{{ $width }}mm" height="{{ $height }}mm" viewBox="0 0 540 856">
    <defs>
        <linearGradient id="brand" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#0c4a6e" />
            <stop offset="0.55" stop-color="#0369a1" />
            <stop offset="1" stop-color="#06b6d4" />
        </linearGradient>
        <linearGradient id="bar" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#22d3ee" />
            <stop offset="1" stop-color="#0369a1" />
        </linearGradient>
    </defs>

    <rect width="540" height="856" fill="#ffffff" />

    <polygon points="540,150 540,470 400,150" fill="#f0f9ff" />
    <circle cx="496" cy="700" r="5" fill="#bae6fd" />
    <circle cx="516" cy="700" r="5" fill="#bae6fd" />
    <circle cx="496" cy="722" r="5" fill="#bae6fd" />
    <circle cx="516" cy="722" r="5" fill="#bae6fd" />

    {{-- Side bar --}}
    <rect x="0" y="150" width="16" height="620" fill="url(#bar)" />

    {{-- Header --}}
    <rect x="0" y="0" width="540" height="118" fill="#22d3ee" />
    <rect x="0" y="0" width="540" height="110" fill="url(#brand)" />
    <polygon points="430,0 470,0 420,110 380,110" fill="#ffffff" fill-opacity="0.07" />
    <polygon points="490,0 516,0 466,110 440,110" fill="#ffffff" fill-opacity="0.07" />

    {{-- Footer --}}
    <rect x="0" y="784" width="540" height="72" fill="#22d3ee" />
    <rect x="0" y="792" width="540" height="64" fill="url(#brand)" />
    <polygon points="430,792 470,792 440,856 400,856" fill="#ffffff" fill-opacity="0.07" />
    <polygon points="490,792 516,792 486,856 460,856" fill="#ffffff" fill-opacity="0.07" />

    {{-- Cut guide --}}
    <rect x="1" y="1" width="538" height="854" fill="none" stroke="#cbd5e1" stroke-width="2" />
</svg>
