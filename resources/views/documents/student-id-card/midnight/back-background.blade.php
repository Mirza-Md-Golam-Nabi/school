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

    <polygon points="540,0 290,0 540,130" fill="#ffffff" fill-opacity="0.03" />

    {{-- Title rule --}}
    <rect x="190" y="98" width="160" height="4" fill="url(#gold)" />

    {{-- White panel: the details, barcode and signature need a light ground --}}
    <rect x="22" y="134" width="496" height="662" fill="url(#gold)" />
    <rect x="28" y="140" width="484" height="650" fill="#ffffff" />

    {{-- Footer --}}
    <polygon points="0,822 540,822 540,856 0,856" fill="url(#gold)" />

    {{-- Cut guide --}}
    <rect x="1" y="1" width="538" height="854" fill="none" stroke="#cbd5e1" stroke-width="2" />
</svg>
