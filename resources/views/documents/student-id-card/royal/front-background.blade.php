<svg xmlns="http://www.w3.org/2000/svg" width="{{ $width }}mm" height="{{ $height }}mm" viewBox="0 0 540 856">
    <defs>
        <linearGradient id="brand" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#1e1b4b" />
            <stop offset="0.55" stop-color="#3730a3" />
            <stop offset="1" stop-color="#7c3aed" />
        </linearGradient>
        <linearGradient id="gold" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0" stop-color="#fcd34d" />
            <stop offset="1" stop-color="#f59e0b" />
        </linearGradient>
    </defs>

    <rect width="540" height="856" fill="#ffffff" />

    {{-- Soft lavender shapes behind the details --}}
    <circle cx="560" cy="600" r="170" fill="#eef2ff" />
    <circle cx="-30" cy="720" r="120" fill="#f5f3ff" />

    {{-- Header: a gold swoosh peeking out from under the brand wave --}}
    <path d="M0,0 H540 V262 C430,352 260,330 150,300 C85,282 32,290 0,316 Z" fill="url(#gold)" />
    <path d="M0,0 H540 V244 C430,334 260,312 150,282 C85,264 32,272 0,298 Z" fill="url(#brand)" />
    <circle cx="486" cy="34" r="120" fill="#ffffff" fill-opacity="0.07" />
    <circle cx="520" cy="150" r="60" fill="#ffffff" fill-opacity="0.06" />
    <circle cx="26" cy="216" r="74" fill="#ffffff" fill-opacity="0.06" />

    {{-- Footer --}}
    <path d="M0,782 C130,744 300,806 540,758 V856 H0 Z" fill="url(#gold)" />
    <path d="M0,796 C130,758 300,820 540,772 V856 H0 Z" fill="url(#brand)" />

    {{-- Cut guide --}}
    <rect x="1" y="1" width="538" height="854" fill="none" stroke="#cbd5e1" stroke-width="2" />
</svg>
