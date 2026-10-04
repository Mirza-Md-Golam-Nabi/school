@php
    // Previews are drawn at this many pixels per millimetre of the real card,
    // using the same positions the printed sheet uses.
    $u = 2.6;
    $px = fn (float $mm): string => round($mm * $u, 1).'px';
    $pt = fn (float $points): string => round($points * 0.3528 * $u, 1).'px';
    $box = fn (float $left, float $top, float $width, ?float $height = null): string => sprintf(
        'position:absolute; left:%s; top:%s; width:%s;%s',
        $px($left),
        $px($top),
        $px($width),
        $height === null ? '' : ' height:'.$px($height).';',
    );
@endphp

<x-filament::section
    icon="heroicon-o-swatch"
    heading="Card Design"
    description="যে ডিজাইনটা বেছে নেবেন, সব ক্লাসের ID card সেই ডিজাইনে তৈরি হবে। ডিজাইনের ওপর ক্লিক করুন।"
>
    <div class="flex flex-wrap justify-center gap-4 sm:justify-start">
        @foreach ($templates as $template)
            @php
                $theme = $template['theme'];
                $isSelected = $template['value'] === $selectedTemplate;
                $ring = 'rgb('.implode(',', $template['ring']).')';
            @endphp

            <button
                type="button"
                wire:click="selectTemplate('{{ $template['value'] }}')"
                wire:loading.attr="disabled"
                wire:key="id-card-template-{{ $template['value'] }}"
                aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                class="rounded-xl bg-white p-2 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:bg-gray-900"
                style="border: 2px solid {{ $isSelected ? '#16a34a' : 'rgba(148,163,184,0.35)' }}; {{ $isSelected ? 'box-shadow: 0 0 0 3px rgba(22,163,74,0.2);' : '' }}"
            >
                <div class="flex gap-1" style="font-family: ui-sans-serif, system-ui, sans-serif; line-height: 1.15;">
                    {{-- Front --}}
                    <div style="position:relative; overflow:hidden; border-radius:4px; width:{{ $px(54) }}; height:{{ $px(85.6) }};">
                        <img src="{{ $template['front'] }}" alt="" style="position:absolute; inset:0; width:100%; height:100%;">

                        <div style="{{ $box(3, 4.5, 48) }} text-align:center;">
                            <div style="font-size:{{ $pt(8.5) }}; font-weight:700; color:{{ $theme['schoolName'] }}; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $schoolName }}</div>
                            <div style="font-size:{{ $pt(5.2) }}; color:{{ $theme['schoolAddress'] }};">School Address</div>
                        </div>

                        <div style="{{ $box(15.5, 15.5, 23, 23) }} border-radius:9999px; background:#e0e7ff; border:{{ $px(0.55) }} solid {{ $ring }}; box-shadow: inset 0 0 0 {{ $px(0.7) }} #ffffff; overflow:hidden;">
                            <div style="position:absolute; left:35%; top:22%; width:30%; height:30%; border-radius:9999px; background:#a5b4fc;"></div>
                            <div style="position:absolute; left:19%; top:60%; width:62%; height:62%; border-radius:9999px; background:#a5b4fc;"></div>
                        </div>

                        <div style="{{ $box(2, 40, 50) }} text-align:center;">
                            <div style="font-size:{{ $pt(10) }}; font-weight:700; color:{{ $theme['studentName'] }};">Student Name</div>
                            <div style="display:inline-block; margin-top:{{ $px(1) }}; padding:{{ $px(0.5) }} {{ $px(3.5) }}; border-radius:9999px; background:{{ $theme['roleBackground'] }}; color:{{ $theme['roleText'] }}; font-size:{{ $pt(5.2) }}; font-weight:700; letter-spacing:{{ $px(0.5) }};">STUDENT</div>
                        </div>

                        <div style="{{ $box(6, 52, 42) }}">
                            @foreach (['ID NO' => '20260001', 'CLASS' => 'Six', 'ROLL' => '12'] as $label => $value)
                                <div style="display:flex; align-items:center; padding:{{ $px(0.9) }} 0; border-bottom:1px solid {{ $theme['rowBorder'] }};">
                                    <span style="width:{{ $px(16) }}; font-size:{{ $pt(5.2) }}; font-weight:700; color:{{ $theme['label'] }};">{{ $label }}</span>
                                    <span style="font-size:{{ $pt(6.8) }}; font-weight:700; color:{{ $theme['value'] }};">{{ $value }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div style="{{ $box(0, 80.6, 54) }} text-align:center; font-size:{{ $pt(6) }}; font-weight:700; letter-spacing:{{ $px(0.4) }}; color:{{ $theme['footer'] }};">
                            ADMISSION <span style="color:{{ $theme['footerAccent'] }};">{{ now()->year }}</span>
                        </div>
                    </div>

                    {{-- Back --}}
                    <div style="position:relative; overflow:hidden; border-radius:4px; width:{{ $px(54) }}; height:{{ $px(85.6) }};">
                        <img src="{{ $template['back'] }}" alt="" style="position:absolute; inset:0; width:100%; height:100%;">

                        <div style="{{ $box(0, 3.9, 54) }} text-align:center; font-size:{{ $pt(7) }}; font-weight:700; letter-spacing:{{ $px(0.5) }}; color:{{ $theme['backTitle'] }};">STUDENT ID CARD</div>

                        <div style="{{ $box(5, 17, 44) }}">
                            @foreach (['GUARDIAN' => 'Guardian Name', 'PHONE' => '01700-000000', 'BLOOD GROUP' => 'B+'] as $label => $value)
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
                        <div style="{{ $box(29, 68.2, 20) }} border-top:1px solid {{ $theme['fieldValue'] }}; text-align:center; font-size:{{ $pt(5) }}; font-weight:700; color:{{ $theme['fieldValue'] }};">PRINCIPAL</div>
                    </div>
                </div>

                <div class="mt-2 flex items-center justify-between gap-2 px-1">
                    <div class="min-w-0">
                        <p class="truncate text-xs font-bold text-gray-900 sm:text-sm dark:text-white">{{ $template['label'] }}</p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $template['description'] }}</p>
                    </div>

                    @if ($isSelected)
                        <x-filament::badge color="success" icon="heroicon-m-check-circle" size="sm">Selected</x-filament::badge>
                    @endif
                </div>
            </button>
        @endforeach
    </div>
</x-filament::section>
