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

    <circle cx="-40" cy="650" r="140" fill="#f5f3ff" />
    <circle cx="570" cy="300" r="180" fill="#eef2ff" />

    {{-- Header --}}
    <path d="M0,0 H540 V112 C410,156 250,96 0,146 Z" fill="url(#gold)" />
    <path d="M0,0 H540 V100 C410,144 250,84 0,134 Z" fill="url(#brand)" />
    <circle cx="500" cy="10" r="80" fill="#ffffff" fill-opacity="0.07" />

    {{-- Footer --}}
    <path d="M0,770 C200,806 380,740 540,786 V856 H0 Z" fill="url(#gold)" />
    <path d="M0,784 C200,820 380,754 540,800 V856 H0 Z" fill="url(#brand)" />

    {{-- Cut guide --}}
    <rect x="1" y="1" width="538" height="854" fill="none" stroke="#cbd5e1" stroke-width="2" />
</svg>
