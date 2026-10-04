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

    {{-- Pale panel and dot grid behind the details --}}
    <polygon points="540,400 540,770 380,770" fill="#f0f9ff" />
    <circle cx="496" cy="560" r="5" fill="#bae6fd" />
    <circle cx="516" cy="560" r="5" fill="#bae6fd" />
    <circle cx="496" cy="582" r="5" fill="#bae6fd" />
    <circle cx="516" cy="582" r="5" fill="#bae6fd" />
    <circle cx="496" cy="604" r="5" fill="#bae6fd" />
    <circle cx="516" cy="604" r="5" fill="#bae6fd" />
    <circle cx="496" cy="626" r="5" fill="#bae6fd" />
    <circle cx="516" cy="626" r="5" fill="#bae6fd" />

    {{-- Side bar --}}
    <rect x="0" y="330" width="16" height="440" fill="url(#bar)" />

    {{-- Header: a slanted block with a cyan edge and diagonal light streaks --}}
    <polygon points="0,0 540,0 540,186 0,306" fill="#22d3ee" />
    <polygon points="0,0 540,0 540,170 0,290" fill="url(#brand)" />
    <polygon points="396,0 440,0 348,196 304,206" fill="#ffffff" fill-opacity="0.07" />
    <polygon points="462,0 492,0 412,182 382,188" fill="#ffffff" fill-opacity="0.07" />
    <polygon points="516,0 540,0 540,60 476,168 452,173" fill="#ffffff" fill-opacity="0.07" />

    {{-- Footer --}}
    <rect x="0" y="784" width="540" height="72" fill="#22d3ee" />
    <rect x="0" y="792" width="540" height="64" fill="url(#brand)" />
    <polygon points="430,792 470,792 440,856 400,856" fill="#ffffff" fill-opacity="0.07" />
    <polygon points="490,792 516,792 486,856 460,856" fill="#ffffff" fill-opacity="0.07" />

    {{-- Cut guide --}}
    <rect x="1" y="1" width="538" height="854" fill="none" stroke="#cbd5e1" stroke-width="2" />
</svg>
