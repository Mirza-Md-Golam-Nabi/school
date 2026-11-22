@php
    use App\Enums\StudentIdCardTemplate;
    use App\Enums\TeacherIdCardField;

    // Colours laid over the chosen design's artwork.
    $theme ??= StudentIdCardTemplate::Midnight->theme();

    // mpdf only honours absolute positioning on blocks that are direct children
    // of <body>, so each card is assembled from separately positioned layers
    // rather than nested ones. This builds one layer's page-relative box.
    $box = fn (float $left, float $top, float $width, float $height): string => sprintf(
        'left: %.2Fmm; top: %.2Fmm; width: %.2Fmm; height: %.2Fmm;',
        $left,
        $top,
        $width,
        $height,
    );

    // Blood group is the one value picked out in red, wherever it is printed.
    $valueColour = fn (TeacherIdCardField $field, string $shade): string => $field === TeacherIdCardField::BloodGroup ? "color: {$shade};" : '';
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    @include('documents.partials.id-card-sheet-styles')
</head>

<body>
    @foreach ($cards as $card)
        @php
            $teacher = $card['teacher'];
            $frontX = $card['x'];
            $backX = $card['x'] + $cardWidth;
            $y = $card['y'];

            $idNumber = $teacher->idCardNumber();

            // Fields the teacher has nothing recorded for are left off the card.
            $resolve = fn (array $fields) => collect($fields)
                ->map(fn (TeacherIdCardField $field): array => ['field' => $field, 'value' => $field->valueFor($teacher)])
                ->filter(fn (array $detail): bool => $detail['value'] !== null)
                ->values();

            $frontDetails = $resolve($frontFields);
            $backDetails = $resolve($backFields);

            // Fewer rows get more breathing room, so a short list still fills the space.
            $frontRowPadding = match (true) {
                $frontDetails->count() <= 3 => 1.25,
                $frontDetails->count() === 4 => 0.95,
                $frontDetails->count() === 5 => 0.65,
                default => 0.45,
            };
        @endphp

        {{-- ================= Front ================= --}}
        <div class="layer" style="{{ $box($frontX, $y, $cardWidth, $cardHeight) }}">
            <img src="{{ $frontBackground }}" style="width: {{ $cardWidth }}mm; height: {{ $cardHeight }}mm;">
        </div>

        <div class="layer" style="{{ $box($frontX + 3, $y + 3, 48, 11.5) }}">
            <table>
                <tr>
                    @if ($school['logo'])
                        <td class="logo">
                            <img src="{{ $school['logo'] }}" style="width: 8.5mm; height: 8.5mm;">
                        </td>
                    @endif
                    <td class="school" @if (! $school['logo']) style="text-align: center;" @endif>
                        <div class="school-name">{{ $school['name'] }}</div>
                        @if ($school['address'])
                            <div class="school-address">{{ $school['address'] }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <div class="layer" style="{{ $box($frontX + 15.5, $y + 15.5, 23, 23) }}">
            <img src="{{ $card['photo'] }}" style="width: 23mm; height: 23mm;">
        </div>

        <div class="layer" style="{{ $box($frontX + 2, $y + 40, 50, 11) }}">
            <div class="student-name">{{ $teacher->user?->name }}</div>
            <div class="role">TEACHER</div>
        </div>

        <div class="layer" style="{{ $box($frontX + 6, $y + 51.2, 42, 24.3) }}">
            <table class="details">
                @foreach ($frontDetails as $detail)
                    <tr>
                        <td class="label" style="padding: {{ $frontRowPadding }}mm 0;">{{ $detail['field']->shortLabel() }}</td>
                        <td class="value" style="padding: {{ $frontRowPadding }}mm 0; {{ $valueColour($detail['field'], $theme['highlight']) }}">{{ $detail['value'] }}</td>
                    </tr>
                @endforeach
            </table>
        </div>

        @if ($teacher->joining_date)
            <div class="layer" style="{{ $box($frontX, $y + 80.2, $cardWidth, 4) }}">
                <div class="footer">SINCE <span class="accent">{{ $teacher->joining_date->year }}</span></div>
            </div>
        @endif

        {{-- ================= Back ================= --}}
        <div class="layer" style="{{ $box($backX, $y, $cardWidth, $cardHeight) }}">
            <img src="{{ $backBackground }}" style="width: {{ $cardWidth }}mm; height: {{ $cardHeight }}mm;">
        </div>

        <div class="layer" style="{{ $box($backX, $y + 3.4, $cardWidth, 5) }}">
            <div class="back-title">TEACHER ID CARD</div>
        </div>

        <div class="layer" style="{{ $box($backX + 5, $y + 16.5, 44, 32) }}">
            <table class="guardian">
                @foreach ($backDetails as $detail)
                    <tr>
                        <td>
                            <div class="field-label">{{ mb_strtoupper($detail['field']->getLabel()) }}</div>
                            <div class="field-value" style="{{ $valueColour($detail['field'], $theme['backHighlight']) }}">{{ $detail['value'] }}</div>
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>

        <div class="layer" style="{{ $box($backX + 5, $y + 49.5, 44, 11.5) }}">
            <div class="barcode">
                <barcode code="{{ $idNumber }}" type="C128B" size="0.75" height="0.85" />
            </div>
            <div class="barcode barcode-number">{{ $idNumber }}</div>
        </div>

        <div class="layer" style="{{ $box($backX + 5, $y + 61.5, 44, 10.5) }}">
            <table class="signing">
                <tr>
                    <td>
                        ISSUE DATE
                        <div class="validity">{{ mb_strtoupper($issueDate->format('d M Y')) }}</div>
                    </td>
                    <td class="authority">
                        @if ($school['signature'])
                            <img src="{{ $school['signature'] }}" style="width: 16mm; height: 6mm;">
                        @endif
                        <div class="signature-line">{{ mb_strtoupper(\App\Models\SchoolSetting::headSignatoryTitle()) }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="layer" style="{{ $box($backX + 4, $y + 72.6, 46, 5.5) }}">
            <div class="return-note">
                This card is not transferable. If found, please return it to<br>
                {{ $school['name'] }}
            </div>
        </div>
    @endforeach
</body>

</html>
