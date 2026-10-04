@php
    use App\Enums\StudentIdCardField;
    use App\Enums\StudentIdCardTemplate;
    use App\Enums\StudentIdCardValidity;

    // Colours laid over the chosen design's artwork.
    $theme ??= StudentIdCardTemplate::Royal->theme();

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

    // Blood group is the one value picked out in red, wherever it is printed —
    // each side has its own shade, since a design's two sides can differ in ground.
    $valueColour = fn (StudentIdCardField $field, string $shade): string => $field === StudentIdCardField::BloodGroup ? "color: {$shade};" : '';
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 0;
        }

        body {
            font-family: 'SolaimanLipi', sans-serif;
            font-size: 7pt;
            color: {{ $theme['text'] }};
        }

        .layer {
            position: absolute;
            overflow: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        /* ---------- Front ---------- */
        td.logo {
            width: 10mm;
            vertical-align: middle;
        }

        td.school {
            vertical-align: middle;
        }

        .school-name {
            font-size: 8.5pt;
            font-weight: bold;
            color: {{ $theme['schoolName'] }};
            line-height: 1.15;
        }

        .school-address {
            font-size: 5.2pt;
            color: {{ $theme['schoolAddress'] }};
        }

        .student-name {
            font-size: 10pt;
            font-weight: bold;
            color: {{ $theme['studentName'] }};
            text-align: center;
            line-height: 1.15;
        }

        .role {
            width: 20mm;
            margin: 1mm auto 0 auto;
            padding: 0.5mm 0;
            border-radius: 2mm;
            background-color: {{ $theme['roleBackground'] }};
            color: {{ $theme['roleText'] }};
            font-size: 5.2pt;
            font-weight: bold;
            letter-spacing: 0.5mm;
            text-align: center;
        }

        table.details td {
            border-bottom: 0.15mm solid {{ $theme['rowBorder'] }};
            vertical-align: middle;
        }

        table.details td.label {
            width: 16mm;
            font-size: 5.2pt;
            font-weight: bold;
            letter-spacing: 0.15mm;
            color: {{ $theme['label'] }};
        }

        table.details td.value {
            font-size: 6.8pt;
            font-weight: bold;
            color: {{ $theme['value'] }};
        }

        .footer {
            font-size: 6pt;
            font-weight: bold;
            letter-spacing: 0.4mm;
            color: {{ $theme['footer'] }};
            text-align: center;
        }

        .footer .accent {
            color: {{ $theme['footerAccent'] }};
        }

        /* ---------- Back ---------- */
        .back-title {
            font-size: 7pt;
            font-weight: bold;
            letter-spacing: 0.5mm;
            color: {{ $theme['backTitle'] }};
            text-align: center;
        }

        table.guardian td {
            padding: 0.7mm 0;
            vertical-align: top;
        }

        .field-label {
            font-size: 4.8pt;
            font-weight: bold;
            letter-spacing: 0.15mm;
            color: {{ $theme['fieldLabel'] }};
        }

        .field-value {
            font-size: 6.8pt;
            font-weight: bold;
            color: {{ $theme['fieldValue'] }};
            line-height: 1.2;
        }

        .barcode {
            text-align: center;
        }

        .barcode-number {
            font-size: 6pt;
            font-weight: bold;
            letter-spacing: 0.6mm;
            color: {{ $theme['fieldValue'] }};
        }

        table.signing td {
            vertical-align: bottom;
            font-size: 5pt;
            color: {{ $theme['muted'] }};
        }

        table.signing td.authority {
            text-align: center;
            width: 22mm;
        }

        .signature-line {
            border-top: 0.2mm solid {{ $theme['fieldValue'] }};
            padding-top: 0.4mm;
            font-weight: bold;
            color: {{ $theme['fieldValue'] }};
        }

        .validity {
            font-size: 6.4pt;
            font-weight: bold;
            color: {{ $theme['fieldValue'] }};
        }

        .return-note {
            font-size: 4.6pt;
            color: {{ $theme['returnNote'] }};
            text-align: center;
            line-height: 1.3;
        }
    </style>
</head>

<body>
    @foreach ($cards as $card)
        @php
            $student = $card['student'];
            $frontX = $card['x'];
            $backX = $card['x'] + $cardWidth;
            $y = $card['y'];

            $idNumber = $student->idCardNumber();

            // Fields the student has nothing recorded for are left off the card.
            $resolve = fn (array $fields) => collect($fields)
                ->map(fn (StudentIdCardField $field): array => ['field' => $field, 'value' => $field->valueFor($student)])
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
            <div class="student-name">{{ $student->user?->name }}</div>
            <div class="role">STUDENT</div>
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

        @if ($student->admission_date)
            <div class="layer" style="{{ $box($frontX, $y + 80.2, $cardWidth, 4) }}">
                <div class="footer">ADMISSION <span class="accent">{{ $student->admission_date->year }}</span></div>
            </div>
        @endif

        {{-- ================= Back ================= --}}
        <div class="layer" style="{{ $box($backX, $y, $cardWidth, $cardHeight) }}">
            <img src="{{ $backBackground }}" style="width: {{ $cardWidth }}mm; height: {{ $cardHeight }}mm;">
        </div>

        <div class="layer" style="{{ $box($backX, $y + 3.4, $cardWidth, 5) }}">
            <div class="back-title">STUDENT ID CARD</div>
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
                        @if ($validity === StudentIdCardValidity::SessionEnd)
                            VALID TILL
                            <div class="validity">DEC {{ $student->session_year }}</div>
                        @else
                            ISSUE DATE
                            <div class="validity">{{ mb_strtoupper($issueDate->format('d M Y')) }}</div>
                        @endif
                    </td>
                    <td class="authority">
                        @if ($school['signature'])
                            <img src="{{ $school['signature'] }}" style="width: 16mm; height: 6mm;">
                        @endif
                        <div class="signature-line">PRINCIPAL</div>
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
