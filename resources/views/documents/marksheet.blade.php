@php
    use App\Models\SchoolSetting;
    use Illuminate\Support\Facades\Storage;

    $student = $marksheet->student;
    $exam = $marksheet->exam;

    $schoolName = SchoolSetting::get('school_name', '');
    $schoolAddress = SchoolSetting::get('school_address', '');
    $schoolEstablishedYear = SchoolSetting::get('school_established_year', '');
    $schoolAddressLine = collect([
        $schoolAddress,
        $schoolEstablishedYear ? "Established: {$schoolEstablishedYear}" : null,
    ])->filter()->implode(' | ');
    $useLogo = (bool) SchoolSetting::get('marksheet_use_logo', '1');
    $useWatermark = (bool) SchoolSetting::get('marksheet_use_watermark', '0');
    $watermarkText = SchoolSetting::get('marksheet_watermark_text', '');
    $footerText = SchoolSetting::get('marksheet_footer_text', '');

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

    $contributionSourceName = $rows->first(fn (array $row) => $row['contribution'] !== null)['contribution']['source_name'] ?? null;
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 28px;
        }

        body {
            font-family: 'SolaimanLipi', sans-serif;
            font-size: 12px;
            color: #1a1a1a;
        }

        .sheet {
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
            width: 220px;
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
            margin-bottom: 10px;
        }

        table.info td {
            padding: 4px 6px;
            vertical-align: top;
        }

        table.info td.label {
            font-weight: bold;
            width: 110px;
        }

        table.subjects {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        table.subjects th,
        table.subjects td {
            border: 1px solid #999;
            padding: 5px 6px;
            font-size: 11px;
        }

        table.subjects th {
            background: #f2f2f2;
        }

        table.summary {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        table.summary td {
            border: 1px solid #999;
            padding: 6px 8px;
            font-size: 11px;
        }

        table.summary td.summary-label {
            font-weight: bold;
            background: #f2f2f2;
            width: 25%;
        }

        .signatures {
            margin-top: 20px;
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .signatures td {
            text-align: center;
            font-size: 11px;
            width: 25%;
            vertical-align: bottom;
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

    <div class="sheet">
        <div class="header">
            @if ($logoDataUri)
                <img class="logo" src="{{ $logoDataUri }}" alt="Logo">
            @endif
            <div class="school-name">{{ $schoolName }}</div>
            @if ($schoolAddressLine)
                <div class="school-address">{{ $schoolAddressLine }}</div>
            @endif
        </div>

        <div class="title">Marksheet - {{ $exam->examType?->name }} {{ $exam->session_year }}</div>

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
            @if ($student->section)
                <tr>
                    <td class="label">Section</td>
                    <td colspan="3">{{ $student->section?->name }}</td>
                </tr>
            @endif
        </table>

        <table class="subjects">
            <thead>
                <tr>
                    <th style="text-align: left;">Subject</th>
                    <th>MCQ</th>
                    <th>Written</th>
                    <th>Practical</th>
                    @if ($contributionSourceName)
                        <th>{{ $contributionSourceName }}</th>
                    @endif
                    <th>Marks</th>
                    <th>Best</th>
                    <th>Grade</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['subject_name'] }}</td>
                        <td style="text-align: center;">{{ $row['mcq_marks'] ?? '-' }}</td>
                        <td style="text-align: center;">{{ $row['written_marks'] ?? '-' }}</td>
                        <td style="text-align: center;">{{ $row['practical_marks'] ?? '-' }}</td>
                        @if ($contributionSourceName)
                            <td style="text-align: center;">{{ $row['contribution']['contributed_marks'] ?? '-' }}</td>
                        @endif
                        <td style="text-align: center;">
                            @if ($row['is_absent'])
                                Absent
                            @else
                                {{ $row['total_marks'] }}
                            @endif
                        </td>
                        <td style="text-align: center;">{{ $row['best_marks'] ?? '-' }}</td>
                        <td style="text-align: center; font-weight: bold;">
                            {{ $row['grade_label'] ?? '-' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="summary">
            <tr>
                <td class="summary-label">Total Marks</td>
                <td>{{ $summary['total_marks'] }}</td>
                <td class="summary-label">GPA</td>
                <td>{{ $summary['gpa'] }} ({{ $summary['overall_grade_label'] }})</td>
            </tr>
            <tr>
                <td class="summary-label">Class Rank</td>
                <td>{{ $summary['class_rank'] ?? '-' }}</td>
                <td class="summary-label">Section Rank</td>
                <td>{{ $summary['section_rank'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="summary-label">1st Position Total Marks</td>
                <td>{{ $summary['top_rank_total_marks'] ?? '-' }}</td>
                <td class="summary-label">1st Position GPA</td>
                <td>
                    @if ($summary['top_rank_gpa'])
                        {{ $summary['top_rank_gpa'] }} ({{ $summary['top_rank_grade_label'] }})
                    @else
                        -
                    @endif
                </td>
            </tr>
        </table>

        <table class="signatures">
            <tr>
                <td>
                    <br><br><br>
                    Class Teacher
                </td>
                <td>
                    <br><br><br>
                    Guardian
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

        @if ($footerText)
            <div class="footer">{{ $footerText }}</div>
        @endif
    </div>
</body>

</html>
