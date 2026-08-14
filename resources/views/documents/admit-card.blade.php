@php
    use App\Models\SchoolSetting;
    use Illuminate\Support\Facades\Storage;

    $student = $admitCard->student;
    $exam = $admitCard->exam;

    $schoolName = SchoolSetting::get('school_name', '');
    $schoolAddress = SchoolSetting::get('school_address', '');
    $schoolEstablishedYear = SchoolSetting::get('school_established_year', '');
    $schoolAddressLine = collect([
        $schoolAddress,
        $schoolEstablishedYear ? "Established: {$schoolEstablishedYear}" : null,
    ])->filter()->implode(' | ');
    $useLogo = (bool) SchoolSetting::get('admit_card_use_logo', '1');
    $useWatermark = (bool) SchoolSetting::get('admit_card_use_watermark', '0');
    $watermarkText = SchoolSetting::get('admit_card_watermark_text', '');
    $footerText = SchoolSetting::get('admit_card_footer_text', '');

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

    $logoDataUri = $useLogo ? $toDataUri(SchoolSetting::get('school_logo')) : null;
    $sealDataUri = $toDataUri(SchoolSetting::get('school_seal'));
    $signatureDataUri = $toDataUri(SchoolSetting::get('principal_signature'));
    $studentPhotoDataUri = $toDataUri($student->user?->avatar);
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 24px;
        }

        body {
            font-family: 'SolaimanLipi', sans-serif;
            font-size: 12px;
            color: #1a1a1a;
        }

        .card {
            border: 2px solid #1a1a1a;
            padding: 16px;
            position: relative;
        }

        .header {
            text-align: center;
            border-bottom: 1px solid #1a1a1a;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .header img.logo {
            height: 50px;
            margin-bottom: 4px;
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
            width: 130px;
            margin: 10px auto;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
            padding: 6px 12px;
            border: 1px solid #1a1a1a;
            border-radius: 8px;
        }

        table.info {
            width: 100%;
            border-collapse: collapse;
        }

        table.info td {
            padding: 4px 6px;
            vertical-align: top;
        }

        table.info td.label {
            font-weight: bold;
            width: 130px;
        }

        .photo {
            position: absolute;
            top: 70px;
            right: 16px;
            width: 80px;
            height: 100px;
            border: 1px solid #1a1a1a;
            object-fit: cover;
        }

        table.subjects {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        table.subjects th,
        table.subjects td {
            border: 1px solid #999;
            padding: 4px 6px;
            font-size: 11px;
        }

        table.subjects th {
            background: #f2f2f2;
        }

        .signatures {
            margin-top: 15px;
            width: 100%;
        }

        .signatures td {
            text-align: center;
            font-size: 11px;
        }

        .signatures img {
            width: 40px;
        }

        .footer {
            margin-top: 16px;
            text-align: center;
            font-size: 10px;
            color: #555;
        }

        .watermark {
            position: fixed;
            top: 40%;
            left: 15%;
            font-size: 48px;
            color: #e0e0e0;
            transform: rotate(-30deg);
            z-index: -1;
        }
    </style>
</head>

<body>
    @if ($useWatermark && $watermarkText)
        <div class="watermark">{{ $watermarkText }}</div>
    @endif

    <div class="card">
        <div class="header">
            @if ($logoDataUri)
                <img class="logo" src="{{ $logoDataUri }}" alt="Logo">
            @endif
            <div class="school-name">{{ $schoolName }}</div>
            @if ($schoolAddressLine)
                <div class="school-address">{{ $schoolAddressLine }}</div>
            @endif
        </div>

        <div class="title">Admit Card</div>

        <table class="info">
            <tr>
                <td class="label">Student Name</td>
                <td colspan="3">{{ $student->user?->name }}</td>
            </tr>
            <tr>
                <td class="label">Class</td>
                <td>{{ $student->class?->name }}</td>
                <td class="label">Roll No</td>
                <td>{{ sprintf('%02d', $student->roll_no) }}</td>
            </tr>
            @if ($student->group && $student->section)
                <tr>
                    <td class="label">Group</td>
                    <td>{{ $student->group?->name }}</td>
                    <td class="label">Section</td>
                    <td>{{ $student->section?->name }}</td>
                </tr>
            @elseif($student->group)
                <tr>
                    <td class="label">Group</td>
                    <td colspan="3">{{ $student->group?->name }}</td>
                </tr>
            @elseif($student->section)
                <tr>
                    <td class="label">Section</td>
                    <td colspan="3">{{ $student->section?->name }}</td>
                </tr>
            @endif
            <tr>
                <td class="label">Exam</td>
                <td colspan="3">{{ $exam->examType?->name }} {{" - "}} {{ $exam->session_year }}</td>
            </tr>
            <tr>
                <td class="label">Exam Period</td>
                <td colspan="3">{{ $exam->start_date?->format('d M Y') }} to
                    {{ $exam->end_date?->format('d M Y') }}</td>
            </tr>
        </table>

        <table class="signatures">
            <tr>
                <td>
                    @if ($sealDataUri)
                        <img src="{{ $sealDataUri }}" alt="Seal" width="50px"><br><br>
                    @endif
                    School Seal
                </td>
                <td>
                    @if ($signatureDataUri)
                        <img src="{{ $signatureDataUri }}" alt="Signature" width="80px"><br>
                    @endif
                    Principal's Signature
                </td>
            </tr>
        </table>

        @if ($footerText)
            <div class="footer">{{ $footerText }}</div>
        @endif
    </div>
</body>

</html>
