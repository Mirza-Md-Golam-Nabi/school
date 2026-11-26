@php
    use App\Models\SchoolSetting;
    use App\Support\PdfImage;
    use App\Support\BengaliNumber;

    $schoolName = SchoolSetting::get('school_name', '');
    $schoolEiinLine = SchoolSetting::eiinLine();
    $schoolAddress = SchoolSetting::get('school_address', '');
    $schoolEstablishedYear = SchoolSetting::get('school_established_year', '');
    $schoolAddressLine = collect([
        $schoolAddress,
        $schoolEstablishedYear ? "Established: {$schoolEstablishedYear}" : null,
    ])->filter()->implode(' | ');

    $imageSrc = fn (?string $path): ?string => PdfImage::path($path);

    $sealSrc = $imageSrc(SchoolSetting::get('school_seal'));
    $signatureSrc = $imageSrc(SchoolSetting::get('principal_signature'));
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 12mm 16mm;
        }

        body {
            font-family: 'SolaimanLipi', sans-serif;
            font-size: 12px;
            color: #1a1a1a;
        }

        .header {
            text-align: center;
            border-bottom: 1px solid #1a1a1a;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        .exam-info {
            text-align: center;
            margin-bottom: 8px;
        }

        .school-name {
            font-size: 18px;
            font-weight: bold;
        }

        .school-address {
            font-size: 11px;
            color: #444;
        }

        .title {
            margin-top: 8px;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .subtitle {
            margin-top: 2px;
            font-size: 11px;
            color: #444;
        }

        table.schedule {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }

        table.schedule th,
        table.schedule td {
            border: 1px solid #999;
            padding: 6px 8px;
        }

        table.schedule th {
            background: #f2f2f2;
            text-align: left;
        }

        table.schedule td.date-col,
        table.schedule th.date-col {
            width: 140px;
            text-align: center;
        }

        table.schedule td.day-col,
        table.schedule th.day-col {
            width: 100px;
            text-align: center;
        }

        .signatures {
            margin-top: 25px;
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .signatures td {
            text-align: center;
            font-size: 11px;
            width: 33.33%;
            vertical-align: bottom;
        }

        .signatures img {
            width: 40px;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="school-name">{{ $schoolName }}</div>
        @if ($schoolEiinLine)
            <div class="school-address">{{ $schoolEiinLine }}</div>
        @endif
        @if ($schoolAddressLine)
            <div class="school-address">{{ $schoolAddressLine }}</div>
        @endif
    </div>
    <div class="exam-info">
        <div class="title">{{ __('Exam Schedule') }}</div>
        <div class="subtitle">{{ $exam->examType?->name }} - {{ $exam->class?->display_name }} ({{ BengaliNumber::localize($exam->session_label) }})</div>
    </div>

    <table class="schedule">
        <thead>
            <tr>
                <th>{{ __('Subject') }}</th>
                <th class="date-col">{{ __('Date') }}</th>
                <th class="day-col">{{ __('Day') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($schedules as $schedule)
                <tr>
                    <td>{{ $exam->subjectDisplayName($schedule->subject) }}</td>
                    <td class="date-col">{{ BengaliNumber::localize($schedule->exam_date->translatedFormat('d F Y')) }}</td>
                    <td class="day-col">{{ $schedule->exam_date->translatedFormat('l') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td>
                <br><br><br>
                {{ __('Class Teacher') }}
            </td>
            <td>
                @if ($sealSrc)
                    <img src="{{ $sealSrc }}" alt="Seal" width="50px"><br><br>
                @else
                    <br><br>
                @endif
                {{ __('School Seal') }}
            </td>
            <td>
                @if ($signatureSrc)
                    <img src="{{ $signatureSrc }}" alt="Signature" width="80px"><br><br>
                @else
                    <br><br>
                @endif
                {{ SchoolSetting::headSignatoryTitle() }}
            </td>
        </tr>
    </table>
</body>

</html>
