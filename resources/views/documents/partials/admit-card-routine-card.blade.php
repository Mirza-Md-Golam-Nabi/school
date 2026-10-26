@php
    /**
     * One admit card with the exam routine below it (Exam → Exam Schedule:
     * date and subject, numbered, half on the left and the rest on the right).
     * Shared by the single-card PDF and the class sheet.
     *
     * @var \App\Models\AdmitCard $admitCard
     * @var \App\Support\AdmitCardBranding $branding
     */
    use App\Support\AdmitCardBranding;

    $student = $admitCard->student;
    $exam = $admitCard->exam;
    $photoDataUri = AdmitCardBranding::dataUri($student->photo);
    [$leftRoutine, $rightRoutine] = $exam->admitCardRoutineColumns();

    $details = collect([
        ['Student Name', $student->user?->name],
        ['Class', $student->class?->display_name],
        ['Roll No', sprintf('%02d', $student->roll_no)],
        ['Section', $student->section?->name],
        ['Group', $student->group?->name],
        ['Reg. No', $student->registration_no],
        ['Academic Year', $exam->session_year],
    ])->filter(fn (array $detail): bool => filled($detail[1]))->values();
@endphp

<div class="arc-card">
    <table class="arc-stripe">
        <tr>
            <td style="background-color: #7fd6a8;"></td>
            <td style="background-color: #8cc2f5;"></td>
            <td style="background-color: #f9b98c;"></td>
            <td style="background-color: #c3a8f2;"></td>
        </tr>
    </table>

    <table class="arc-head">
        <tr>
            <td class="arc-logo-cell">
                @if ($branding->logoDataUri)
                    <img src="{{ $branding->logoDataUri }}" alt="Logo" width="48" height="48" style="width: 48px; height: 48px;">
                @endif
            </td>
            <td style="text-align: center;">
                <div class="arc-school-name">{{ $branding->schoolName }}</div>
                @if ($branding->addressLine)
                    <div class="arc-school-address">{{ $branding->addressLine }}</div>
                @endif
            </td>
            <td class="arc-logo-cell"></td>
        </tr>
    </table>

    {{-- Plain divs, not a table cell: mPDF rounds corners only outside tables. --}}
    <div class="arc-title">
        <div class="arc-pill">ADMIT CARD</div>
        {{-- SolaimanLipi has no dash glyphs, so no "—" here. --}}
        <div class="arc-exam-name">{{ $exam->examType?->name }} ({{ $exam->session_year }})</div>
    </div>

    <table class="arc-body">
        <tr>
            <td class="arc-info-cell">
                <table class="arc-info">
                    @foreach ($details->chunk(2) as $pair)
                        <tr>
                            @foreach ($pair as [$label, $value])
                                <td class="arc-label">{{ $label }}</td>
                                <td class="arc-value">{{ $value }}</td>
                            @endforeach
                            @if ($pair->count() === 1)
                                <td class="arc-label"></td>
                                <td class="arc-value"></td>
                            @endif
                        </tr>
                    @endforeach
                </table>
            </td>
            <td class="arc-photo-cell">
                @if ($photoDataUri)
                    <img class="arc-photo" src="{{ $photoDataUri }}" alt="Photo" width="74" height="88" style="width: 74px; height: 88px;">
                @else
                    {{-- A table cell, not a div: mPDF ignores a div's size inside a table. --}}
                    <table class="arc-photo-placeholder" align="right">
                        <tr>
                            <td style="width: 74px; height: 88px;">Photo</td>
                        </tr>
                    </table>
                @endif
            </td>
        </tr>
    </table>

    <div class="arc-section-title">EXAMINATION ROUTINE</div>

    <table class="arc-routine-wrap">
        <tr>
            @foreach ([$leftRoutine, $rightRoutine] as $column)
                <td class="arc-routine-col">
                    @if ($column)
                        <table class="arc-routine">
                            <tr>
                                <th>#</th>
                                <th>DATE</th>
                                <th>SUBJECT</th>
                            </tr>
                            @foreach ($column as $row)
                                <tr @class(['arc-even' => $loop->even])>
                                    <td class="arc-serial">{{ sprintf('%02d', $row['serial']) }}</td>
                                    <td class="arc-date">{{ $row['date'] }}</td>
                                    <td>{{ $row['subject'] }}</td>
                                </tr>
                            @endforeach
                        </table>
                    @endif
                </td>
            @endforeach
        </tr>
    </table>

    <table class="arc-signatures">
        <tr>
            <td>
                @if ($branding->sealDataUri)
                    <img src="{{ $branding->sealDataUri }}" alt="Seal" width="34" height="34" style="width: 34px; height: 34px;"><br>
                @endif
                <div class="arc-sign-line">School Seal</div>
            </td>
            <td>
                @if ($branding->signatureDataUri)
                    <img src="{{ $branding->signatureDataUri }}" alt="Signature" width="70" height="26" style="width: 70px; height: 26px;"><br>
                @endif
                <div class="arc-sign-line">Principal's Signature</div>
            </td>
        </tr>
    </table>

    <div class="arc-footer">
        @if ($branding->footerText)
            {{ $branding->footerText }}<br>
        @endif
        Powered by: <span class="arc-brand">Next Fusion</span>
    </div>
</div>
