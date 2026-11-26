@php
    // The card preview is drawn at this many pixels per millimetre of the real
    // card, using the same positions the printed sheet uses.
    $u = 4;
    $px = fn (float $mm): string => round($mm * $u, 1).'px';
    $pt = fn (float $points): string => round($points * 0.3528 * $u, 1).'px';
    $box = fn (float $left, float $top, float $width, ?float $height = null): string => sprintf(
        'position:absolute; left:%s; top:%s; width:%s;%s',
        $px($left),
        $px($top),
        $px($width),
        $height === null ? '' : ' height:'.$px($height).';',
    );

    // The printed portrait is 23mm: a coloured ring, a thin white ring inside
    // it, and the photo shrunk to fit within both. The previews draw the same
    // proportions, so the photo frame is smaller than the whole portrait.
    $renderer = \App\Support\IdCardPhotoRenderer::class;
    $ringRatio = $renderer::GOLD_RING_WIDTH / $renderer::SIZE;
    $whiteRatio = $renderer::WHITE_RING_WIDTH / $renderer::SIZE;
    $photoRatio = 1 - 2 * ($ringRatio + $whiteRatio);

    $cardFrame = round(23 * $u, 1);
    $cardPhoto = round($cardFrame * $photoRatio, 1);
    $editorFrame = 220;
    $editorPhoto = round($editorFrame * $photoRatio, 1);

    // Coloured ring, then white ring, drawn as nested padding around the photo.
    $rings = fn (float $frame): string => sprintf(
        'padding:%spx; background:%s; border-radius:9999px;',
        round($frame * $ringRatio, 2),
        $ring,
    );
    $whiteRing = fn (float $frame): string => sprintf(
        'padding:%spx; background:#ffffff; border-radius:9999px;',
        round($frame * $whiteRatio, 2),
    );

    $isBloodGroup = fn (array $detail): bool => $detail['field'] === \App\Enums\TeacherIdCardField::BloodGroup;
@endphp

<x-filament-panels::page>
    <div
        wire:ignore
        x-data="{
            x: @js($position->x),
            y: @js($position->y),
            zoom: @js($position->zoom),
            naturalWidth: 0,
            naturalHeight: 0,
            dragging: false,
            dragFrame: 0,
            pointerX: 0,
            pointerY: 0,
            startX: 0,
            startY: 0,
            saving: false,

            clamp(value) {
                return Math.min(100, Math.max(0, value))
            },

            measure(image) {
                if (image && image.complete && image.naturalWidth) {
                    this.naturalWidth = image.naturalWidth
                    this.naturalHeight = image.naturalHeight
                }
            },

            // The photo scaled so its shorter side fills the frame, times the zoom —
            // the same maths the PDF uses to cut the portrait.
            size(frame) {
                if (! this.naturalWidth) {
                    return { width: frame, height: frame }
                }

                const scale = frame * this.zoom / Math.min(this.naturalWidth, this.naturalHeight)

                return { width: this.naturalWidth * scale, height: this.naturalHeight * scale }
            },

            imageStyle(frame) {
                const { width, height } = this.size(frame)

                return `width:${width}px; height:${height}px; left:${-(width - frame) * this.x / 100}px; top:${-(height - frame) * this.y / 100}px;`
            },

            startDrag(event, frame) {
                this.dragging = true
                this.dragFrame = frame
                this.pointerX = event.clientX
                this.pointerY = event.clientY
                this.startX = this.x
                this.startY = this.y
                event.currentTarget.setPointerCapture?.(event.pointerId)
            },

            drag(event) {
                if (! this.dragging) {
                    return
                }

                const { width, height } = this.size(this.dragFrame)
                const slackX = width - this.dragFrame
                const slackY = height - this.dragFrame

                if (slackX > 0) {
                    this.x = this.clamp(this.startX - (event.clientX - this.pointerX) / slackX * 100)
                }

                if (slackY > 0) {
                    this.y = this.clamp(this.startY - (event.clientY - this.pointerY) / slackY * 100)
                }
            },

            endDrag() {
                this.dragging = false
            },

            async save() {
                this.saving = true
                await $wire.savePhotoPosition(this.x, this.y, this.zoom)
                this.saving = false
            },

            async reset() {
                this.x = {{ \App\Support\IdCardPhotoPosition::DEFAULT_X }}
                this.y = {{ round(\App\Support\IdCardPhotoPosition::DEFAULT_Y, 4) }}
                this.zoom = {{ \App\Support\IdCardPhotoPosition::MIN_ZOOM }}
                await $wire.resetPhotoPosition()
            },
        }"
        class="flex flex-col gap-6 lg:flex-row lg:items-start"
    >
        {{-- ================= Photo editor ================= --}}
        <x-filament::section
            icon="heroicon-o-arrows-pointing-out"
            heading="ছবির পজিশন"
            description="ছবি টেনে সরান, নিচের slider দিয়ে zoom করুন। পাশের কার্ডে সাথে সাথে দেখা যাবে — ঠিক হলে Save চাপুন।"
            class="lg:w-80 lg:shrink-0"
        >
            @if ($photoUrl)
                <div class="flex flex-col items-center gap-4">
                    <div style="{{ $rings($editorFrame) }}">
                        <div style="{{ $whiteRing($editorFrame) }}">
                            <div
                                class="relative cursor-grab touch-none select-none overflow-hidden rounded-full bg-white"
                                :class="dragging && 'cursor-grabbing'"
                                style="width: {{ $editorPhoto }}px; height: {{ $editorPhoto }}px;"
                                x-on:pointerdown.prevent="startDrag($event, {{ $editorPhoto }})"
                                x-on:pointermove="drag($event)"
                                x-on:pointerup="endDrag()"
                                x-on:pointercancel="endDrag()"
                            >
                                <img
                                    x-ref="editorPhoto"
                                    src="{{ $photoUrl }}"
                                    alt=""
                                    draggable="false"
                                    class="pointer-events-none absolute max-w-none"
                                    :style="imageStyle({{ $editorPhoto }})"
                                    x-init="measure($el)"
                                    x-on:load="measure($el)"
                                >
                            </div>
                        </div>
                    </div>

                    <label class="flex w-full items-center gap-3 text-xs text-gray-600 sm:text-sm dark:text-gray-300">
                        <x-heroicon-o-magnifying-glass-minus class="h-4 w-4 shrink-0" />
                        <input
                            type="range"
                            min="{{ \App\Support\IdCardPhotoPosition::MIN_ZOOM }}"
                            max="{{ \App\Support\IdCardPhotoPosition::MAX_ZOOM }}"
                            step="0.01"
                            x-model.number="zoom"
                            class="w-full accent-primary-600"
                            aria-label="Zoom"
                        >
                        <x-heroicon-o-magnifying-glass-plus class="h-4 w-4 shrink-0" />
                    </label>

                    <div class="flex w-full flex-wrap items-center justify-center gap-2">
                        <x-filament::button
                            icon="heroicon-o-check"
                            size="sm"
                            x-on:click="save()"
                            x-bind:disabled="saving"
                        >
                            Save
                        </x-filament::button>

                        <x-filament::button
                            icon="heroicon-o-arrow-uturn-left"
                            size="sm"
                            color="gray"
                            x-on:click="reset()"
                        >
                            Reset
                        </x-filament::button>
                    </div>
                </div>
            @else
                <div class="flex flex-col items-center gap-3 text-center">
                    <div class="flex h-24 w-24 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                        <x-heroicon-o-user class="h-10 w-10 text-gray-400" />
                    </div>
                    <p class="text-xs text-gray-600 sm:text-sm dark:text-gray-300">
                        আপনার এখনো কোনো profile photo নেই — কার্ডে একটা খালি ছবি ছাপা হবে।
                    </p>
                    <x-filament::button tag="a" :href="filament()->getProfileUrl()" icon="heroicon-o-camera" size="sm">
                        Profile Photo যোগ করুন
                    </x-filament::button>
                </div>
            @endif
        </x-filament::section>

        {{-- ================= Card preview ================= --}}
        <x-filament::section
            icon="heroicon-o-identification"
            heading="কার্ডের প্রিভিউ"
            description="প্রিন্ট করা কার্ড দেখতে প্রায় এমনই হবে। কার্ডে কোন তথ্য থাকবে তা স্কুল ঠিক করে।"
            class="min-w-0 flex-1"
        >
            <x-slot name="headerEnd">
                <x-filament::button
                    tag="a"
                    :href="route('teacher-id-cards.teacher.view', ['teacher' => $teacher->id])"
                    target="_blank"
                    icon="heroicon-o-document-text"
                    size="xs"
                    color="gray"
                >
                    PDF দেখুন
                </x-filament::button>
            </x-slot>

            <div class="flex flex-wrap justify-center gap-3 overflow-x-auto" style="font-family: ui-sans-serif, system-ui, sans-serif; line-height: 1.15;">
                {{-- Front --}}
                <div class="shrink-0 shadow-md" style="position:relative; overflow:hidden; border-radius:6px; width:{{ $px($cardWidth) }}; height:{{ $px($cardHeight) }};">
                    <img src="{{ $front }}" alt="" style="position:absolute; inset:0; width:100%; height:100%;">

                    <div style="{{ $box(3, 3, 48, 11.5) }} display:flex; align-items:center; gap:{{ $px(1.5) }}; {{ $schoolLogoUrl ? '' : 'justify-content:center; text-align:center;' }}">
                        @if ($schoolLogoUrl)
                            <img src="{{ $schoolLogoUrl }}" alt="" style="width:{{ $px(8.5) }}; height:{{ $px(8.5) }}; object-fit:contain;">
                        @endif
                        <div style="min-width:0;">
                            <div style="font-size:{{ $pt(8.5) }}; font-weight:700; color:{{ $theme['schoolName'] }};">{{ $schoolName }}</div>
                            @if ($schoolAddress)
                                <div style="font-size:{{ $pt(5.2) }}; color:{{ $theme['schoolAddress'] }};">{{ $schoolAddress }}</div>
                            @endif
                        </div>
                    </div>

                    <div style="{{ $box(15.5, 15.5, 23, 23) }} box-sizing:border-box; {{ $rings($cardFrame) }}">
                        <div style="box-sizing:border-box; width:100%; height:100%; {{ $whiteRing($cardFrame) }}">
                            <div
                                style="position:relative; width:100%; height:100%; border-radius:9999px; overflow:hidden; background:{{ $photoUrl ? '#ffffff; cursor:grab; touch-action:none;' : '#e0e7ff;' }}"
                                @if ($photoUrl)
                                    x-on:pointerdown.prevent="startDrag($event, {{ $cardPhoto }})"
                                    x-on:pointermove="drag($event)"
                                    x-on:pointerup="endDrag()"
                                    x-on:pointercancel="endDrag()"
                                @endif
                            >
                                @if ($photoUrl)
                                    <img
                                        src="{{ $photoUrl }}"
                                        alt=""
                                        draggable="false"
                                        class="pointer-events-none absolute max-w-none"
                                        style="position:absolute;"
                                        :style="imageStyle({{ $cardPhoto }})"
                                        x-init="measure($el)"
                                        x-on:load="measure($el)"
                                    >
                                @else
                                    <div style="position:absolute; left:35%; top:22%; width:30%; height:30%; border-radius:9999px; background:#a5b4fc;"></div>
                                    <div style="position:absolute; left:19%; top:60%; width:62%; height:62%; border-radius:9999px; background:#a5b4fc;"></div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div style="{{ $box(2, 40, 50) }} text-align:center;">
                        <div style="font-size:{{ $pt(10) }}; font-weight:700; color:{{ $theme['studentName'] }};">{{ $teacher->user?->name }}</div>
                        <div style="display:inline-block; margin-top:{{ $px(1) }}; padding:{{ $px(0.5) }} {{ $px(3.5) }}; border-radius:9999px; background:{{ $theme['roleBackground'] }}; color:{{ $theme['roleText'] }}; font-size:{{ $pt(5.2) }}; font-weight:700; letter-spacing:{{ $px(0.5) }};">TEACHER</div>
                    </div>

                    <div style="{{ $box(6, 52, 42) }}">
                        @foreach ($frontDetails as $detail)
                            <div style="display:flex; align-items:center; padding:{{ $px(0.9) }} 0; border-bottom:1px solid {{ $theme['rowBorder'] }};">
                                <span style="width:{{ $px(16) }}; flex-shrink:0; font-size:{{ $pt(5.2) }}; font-weight:700; color:{{ $theme['label'] }};">{{ $detail['shortLabel'] }}</span>
                                <span style="min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:{{ $pt(6.8) }}; font-weight:700; color:{{ $isBloodGroup($detail) ? $theme['highlight'] : $theme['value'] }};">{{ $detail['value'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if ($teacher->joining_date)
                        <div style="{{ $box(0, 80.6, 54) }} text-align:center; font-size:{{ $pt(6) }}; font-weight:700; letter-spacing:{{ $px(0.4) }}; color:{{ $theme['footer'] }};">
                            SINCE <span style="color:{{ $theme['footerAccent'] }};">{{ $teacher->joining_date->year }}</span>
                        </div>
                    @endif
                </div>

                {{-- Back --}}
                <div class="shrink-0 shadow-md" style="position:relative; overflow:hidden; border-radius:6px; width:{{ $px($cardWidth) }}; height:{{ $px($cardHeight) }};">
                    <img src="{{ $back }}" alt="" style="position:absolute; inset:0; width:100%; height:100%;">

                    <div style="{{ $box(0, 3.9, 54) }} text-align:center; font-size:{{ $pt(7) }}; font-weight:700; letter-spacing:{{ $px(0.5) }}; color:{{ $theme['backTitle'] }};">TEACHER ID CARD</div>

                    <div style="{{ $box(5, 17, 44, 32) }} overflow:hidden;">
                        @foreach ($backDetails as $detail)
                            <div style="margin-bottom:{{ $px(1.4) }};">
                                <div style="font-size:{{ $pt(4.8) }}; font-weight:700; color:{{ $theme['fieldLabel'] }};">{{ $detail['label'] }}</div>
                                <div style="font-size:{{ $pt(6.8) }}; font-weight:700; word-break:break-word; color:{{ $isBloodGroup($detail) ? $theme['backHighlight'] : $theme['fieldValue'] }};">{{ $detail['value'] }}</div>
                            </div>
                        @endforeach
                    </div>

                    @include('filament.pages.partials.id-card-qr-preview', ['idNumber' => $teacher->idCardNumber(), 'qrImage' => $qrImage])

                    <div style="{{ $box(5, 65, 22) }} font-size:{{ $pt(5) }}; color:{{ $theme['muted'] }};">
                        ISSUE DATE
                        <div style="font-size:{{ $pt(6.4) }}; font-weight:700; color:{{ $theme['fieldValue'] }};">{{ mb_strtoupper($issueDate->format('d M Y')) }}</div>
                    </div>
                    <div style="{{ $box(29, 62, 20) }} text-align:center;">
                        @if ($principalSignatureUrl)
                            <img src="{{ $principalSignatureUrl }}" alt="" style="display:block; margin:0 auto; width:{{ $px(16) }}; height:{{ $px(6) }}; object-fit:contain;">
                        @else
                            <div style="height:{{ $px(6) }};"></div>
                        @endif
                        <div style="border-top:1px solid {{ $theme['fieldValue'] }}; font-size:{{ $pt(5) }}; font-weight:700; color:{{ $theme['fieldValue'] }};">{{ mb_strtoupper($signatoryTitle) }}</div>
                    </div>

                    <div style="{{ $box(4, 72.6, 46) }} text-align:center; font-size:{{ $pt(4.6) }}; line-height:1.3; color:{{ $theme['returnNote'] }};">
                        This card is not transferable. If found, please return it to<br>{{ $schoolName }}
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
