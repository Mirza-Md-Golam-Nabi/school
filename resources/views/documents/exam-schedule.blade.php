@php
    use App\Models\SchoolSetting;
    use Illuminate\Support\Facades\Storage;

    $schoolName = SchoolSetting::get('school_name', '');
    $schoolAddress = SchoolSetting::get('school_address', '');
    $schoolEstablishedYear = SchoolSetting::get('school_established_year', '');
    $schoolAddressLine = collect([
        $schoolAddress,
        $schoolEstablishedYear ? "Established: {$schoolEstablishedYear}" : null,
    ])->filter()->implode(' | ');

    $toDataUri = function (?string $path): ?string {
        if (!$path) {
            return null;
        }

        $disk = Storage::disk('public');

        if (!$disk->exists($path)) {
            return null;
        }

        $mime = $disk->mimeType($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($disk->get($path));
    };

    $sealDataUri = $toDataUri(SchoolSetting::get('school_seal'));
    $signatureDataUri = $toDataUri(SchoolSetting::get('principal_signature'));
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
        @if ($schoolAddressLine)
            <div class="school-address">{{ $schoolAddressLine }}</div>
        @endif
    </div>
    <div class="exam-info">
        <div class="title">Exam Schedule</div>
        <div class="subtitle">{{ $exam->examType?->name }} - {{ $exam->class?->name }} ({{ $exam->session_year }})</div>
    </div>

    <table class="schedule">
        <thead>
            <tr>
                <th>Subject</th>
                <th class="date-col">Date</th>
                <th class="day-col">Day</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($schedules as $schedule)
                <tr>
                    <td>{{ $schedule->subject?->name }}</td>
                    <td class="date-col">{{ $schedule->exam_date->format('d M Y') }}</td>
                    <td class="day-col">{{ $schedule->exam_date->format('l') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td>
                <br><br><br>
                Class Teacher
            </td>
            <td>
                @if ($sealDataUri)
                    <img src="{{ $sealDataUri }}" alt="Seal" width="50px"><br><br>
                @else
                    <br><br>
                @endif
                School Seal
            </td>
            <td>
                @if ($signatureDataUri)
                    <img src="{{ $signatureDataUri }}" alt="Signature" width="80px"><br><br>
                @else
                    <br><br>
                @endif
                Principal's Signature
            </td>
        </tr>
    </table>
</body>

</html>
