{{--
    One design drawn front and back with sample data, at $u pixels per
    millimetre of the real card, using the positions the printed sheet uses.
    Expects $template, $u, $logo, $schoolName, $schoolAddress, $cardRole,
    $sampleName, $sampleFrontRows, $sampleBackRows and $sampleFooter.
--}}
@php
    $theme = $template['theme'];
    $ring = 'rgb('.implode(',', $template['ring']).')';

    $px = fn (float $mm): string => round($mm * $u, 1).'px';
    $pt = fn (float $points): string => round($points * 0.3528 * $u, 1).'px';
    $box = fn (float $left, float $top, float $width, ?float $height = null): string => sprintf(
        'position:absolute; left:%s; top:%s; width:%s;%s',
        $px($left),
        $px($top),
        $px($width),
        $height === null ? '' : ' height:'.$px($height).';',
    );

    // Same ring proportions the printed portrait is baked with: the photo sits
    // inside the thin white ring, never under it.
    $renderer = \App\Support\IdCardPhotoRenderer::class;
    $portrait = 23 * $u;
    $goldRing = round($portrait * $renderer::GOLD_RING_WIDTH / $renderer::SIZE, 2);
    $whiteRing = round($portrait * $renderer::WHITE_RING_WIDTH / $renderer::SIZE, 2);
@endphp

<div class="flex flex-wrap justify-center gap-1" style="font-family: ui-sans-serif, system-ui, sans-serif; line-height: 1.15;">
    {{-- Front --}}
    <div style="position:relative; overflow:hidden; border-radius:4px; width:{{ $px(54) }}; height:{{ $px(85.6) }};">
        <img src="{{ $template['front'] }}" alt="" style="position:absolute; inset:0; width:100%; height:100%;">

        <div style="{{ $box(3, 3, 48, 11.5) }} display:flex; align-items:center; gap:{{ $px(1.5) }}; {{ $logo ? '' : 'justify-content:center; text-align:center;' }}">
            @if ($logo)
                <img src="{{ $logo }}" alt="" style="width:{{ $px(8.5) }}; height:{{ $px(8.5) }}; object-fit:contain; flex-shrink:0;">
            @endif
            <div style="min-width:0;">
                <div style="font-size:{{ $pt(8.5) }}; font-weight:700; color:{{ $theme['schoolName'] }}; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $schoolName }}</div>
                <div style="font-size:{{ $pt(5.2) }}; color:{{ $theme['schoolAddress'] }}; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $schoolAddress ?: 'School Address' }}</div>
            </div>
        </div>

        <div style="{{ $box(15.5, 15.5, 23, 23) }} box-sizing:border-box; border-radius:9999px; background:{{ $ring }}; padding:{{ $goldRing }}px;">
            <div style="box-sizing:border-box; width:100%; height:100%; border-radius:9999px; background:#ffffff; padding:{{ $whiteRing }}px;">
                <div style="position:relative; width:100%; height:100%; border-radius:9999px; background:#e0e7ff; overflow:hidden;">
                    <div style="position:absolute; left:35%; top:22%; width:30%; height:30%; border-radius:9999px; background:#a5b4fc;"></div>
                    <div style="position:absolute; left:19%; top:60%; width:62%; height:62%; border-radius:9999px; background:#a5b4fc;"></div>
                </div>
            </div>
        </div>

        <div style="{{ $box(2, 40, 50) }} text-align:center;">
            <div style="font-size:{{ $pt(10) }}; font-weight:700; color:{{ $theme['studentName'] }};">{{ $sampleName }}</div>
            <div style="display:inline-block; margin-top:{{ $px(1) }}; padding:{{ $px(0.5) }} {{ $px(3.5) }}; border-radius:9999px; background:{{ $theme['roleBackground'] }}; color:{{ $theme['roleText'] }}; font-size:{{ $pt(5.2) }}; font-weight:700; letter-spacing:{{ $px(0.5) }};">{{ $cardRole }}</div>
        </div>

        <div style="{{ $box(6, 52, 42) }}">
            @foreach ($sampleFrontRows as $label => $value)
                <div style="display:flex; align-items:center; padding:{{ $px(0.9) }} 0; border-bottom:1px solid {{ $theme['rowBorder'] }};">
                    <span style="width:{{ $px(16) }}; font-size:{{ $pt(5.2) }}; font-weight:700; color:{{ $theme['label'] }};">{{ $label }}</span>
                    <span style="font-size:{{ $pt(6.8) }}; font-weight:700; color:{{ $theme['value'] }};">{{ $value }}</span>
                </div>
            @endforeach
        </div>

        <div style="{{ $box(0, 80.6, 54) }} text-align:center; font-size:{{ $pt(6) }}; font-weight:700; letter-spacing:{{ $px(0.4) }}; color:{{ $theme['footer'] }};">
            {{ $sampleFooter }} <span style="color:{{ $theme['footerAccent'] }};">{{ now()->year }}</span>
        </div>
    </div>

    {{-- Back --}}
    <div style="position:relative; overflow:hidden; border-radius:4px; width:{{ $px(54) }}; height:{{ $px(85.6) }};">
        <img src="{{ $template['back'] }}" alt="" style="position:absolute; inset:0; width:100%; height:100%;">

        <div style="{{ $box(0, 3.9, 54) }} text-align:center; font-size:{{ $pt(7) }}; font-weight:700; letter-spacing:{{ $px(0.5) }}; color:{{ $theme['backTitle'] }};">{{ $cardRole }} ID CARD</div>

        <div style="{{ $box(5, 17, 44) }}">
            @foreach ($sampleBackRows as $label => $value)
                <div style="margin-bottom:{{ $px(1.4) }};">
                    <div style="font-size:{{ $pt(4.8) }}; font-weight:700; color:{{ $theme['fieldLabel'] }};">{{ $label }}</div>
                    <div style="font-size:{{ $pt(6.8) }}; font-weight:700; color:{{ $label === 'BLOOD GROUP' ? $theme['backHighlight'] : $theme['fieldValue'] }};">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        <div style="{{ $box(9, 50.5, 36, 6.5) }} background: repeating-linear-gradient(90deg, #111827 0, #111827 1.5px, #ffffff 1.5px, #ffffff 3px, #111827 3px, #111827 4px, #ffffff 4px, #ffffff 6.5px);"></div>
        <div style="{{ $box(5, 57.6, 44) }} text-align:center; font-size:{{ $pt(6) }}; font-weight:700; letter-spacing:{{ $px(0.6) }}; color:{{ $theme['fieldValue'] }};">20260001</div>

        <div style="{{ $box(5, 65, 22) }} font-size:{{ $pt(5) }}; color:{{ $theme['muted'] }};">
            ISSUE DATE
            <div style="font-size:{{ $pt(6.4) }}; font-weight:700; color:{{ $theme['fieldValue'] }};">01 JAN {{ now()->year }}</div>
        </div>
        <div style="{{ $box(29, 68.2, 20) }} border-top:1px solid {{ $theme['fieldValue'] }}; text-align:center; font-size:{{ $pt(5) }}; font-weight:700; color:{{ $theme['fieldValue'] }};">{{ mb_strtoupper(\App\Models\SchoolSetting::headSignatoryTitle()) }}</div>
    </div>
</div>
