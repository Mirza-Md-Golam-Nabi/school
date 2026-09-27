@php
    use App\Models\GradeScale;
    use App\Models\SchoolSetting;
    use App\Support\BengaliNumber;
    use Illuminate\Support\Facades\Storage;

    $student = $marksheet->student;
    $exam = $marksheet->exam;
    $hasSections = $exam->class?->sections()->exists() ?? false;

    $gradeScales = GradeScale::cached();
    $formatMark = fn (float $mark): string => rtrim(rtrim(number_format($mark, 2), '0'), '.');

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

        table.top-section {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        table.top-section td.info-col {
            width: 62%;
            vertical-align: top;
            padding-right: 10px;
        }

        table.top-section td.grade-scale-col {
            width: 38%;
            vertical-align: top;
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
            width: 110px;
        }

        table.grade-scale {
            width: 100%;
            border-collapse: collapse;
        }

        table.grade-scale th,
        table.grade-scale td {
            border: 1px solid #999;
            padding: 3px 5px;
            font-size: 10px;
            text-align: center;
        }

        table.grade-scale th {
            background: #f2f2f2;
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

        .summary-heading {
            margin-top: 14px;
            font-size: 13px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }

        table.summary {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
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

        <div class="title">{{ __('Marksheet') }} - {{ $exam->examType?->name }} {{ BengaliNumber::localize((string) $exam->session_year) }}</div>

        <table class="top-section">
            <tr>
                <td class="info-col">
                    <table class="info">
                        <tr>
                            <td class="label">{{ __('Student Name') }}</td>
                            <td colspan="3">{{ $student->user?->name }}</td>
                        </tr>
                        <tr>
                            <td class="label">{{ __('Class') }}</td>
                            <td>{{ $student->class?->display_name }}</td>
                        </tr>
                        <tr>
                            <td class="label">{{ __('Roll No') }}</td>
                            <td>{{ BengaliNumber::localize(sprintf('%02d', $student->roll_no)) }}</td>
                        </tr>
                        @if ($student->group)
                            <tr>
                                <td class="label">{{ __('Group') }}</td>
                                <td colspan="3">{{ $student->group?->display_name }}</td>
                            </tr>
                        @endif
                        @if ($student->section)
                            <tr>
                                <td class="label">{{ __('Section') }}</td>
                                <td colspan="3">{{ $student->section?->name }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td class="label">{{ __('Session') }}</td>
                            <td colspan="3">{{ BengaliNumber::localize((string) $student->session_year) }}</td>
                        </tr>
                    </table>
                </td>
                <td class="grade-scale-col">
                    @if ($gradeScales->isNotEmpty())
                        <table class="grade-scale">
                            <thead>
                                <tr>
                                    <th>{{ __('Letter Grade') }}</th>
                                    <th>{{ __('Marks Interval') }}</th>
                                    <th>{{ __('Grade Point') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($gradeScales as $scale)
                                    <tr>
                                        <td>{{ $scale->letter_grade }}</td>
                                        <td>{{ BengaliNumber::localize($formatMark($scale->min_mark) . ' - ' . $formatMark($scale->max_mark)) }}</td>
                                        <td>{{ BengaliNumber::localize(number_format($scale->grade_point, 2)) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </td>
            </tr>
        </table>

        <table class="subjects">
            <thead>
                <tr>
                    <th style="text-align: left;">{{ __('Subject') }}</th>
                    <th>{{ __('MCQ') }}</th>
                    <th>{{ __('Written') }}</th>
                    <th>{{ __('Practical') }}</th>
                    @if ($contributionSourceName)
                        <th>{{ $contributionSourceName }}</th>
                    @endif
                    <th>{{ __('Marks') }}</th>
                    <th>{{ __('Best') }}</th>
                    <th>{{ __('Grade') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['subject_name'] }}</td>
                        <td style="text-align: center;">{{ BengaliNumber::localize((string) ($row['mcq_marks'] ?? '-')) }}</td>
                        <td style="text-align: center;">{{ BengaliNumber::localize((string) ($row['written_marks'] ?? '-')) }}</td>
                        <td style="text-align: center;">{{ BengaliNumber::localize((string) ($row['practical_marks'] ?? '-')) }}</td>
                        @if ($contributionSourceName)
                            <td style="text-align: center;">{{ BengaliNumber::localize((string) ($row['contribution']['contributed_marks'] ?? '-')) }}</td>
                        @endif
                        <td style="text-align: center;">
                            @if ($row['is_absent'])
                                {{ __('Absent') }}
                            @else
                                {{ BengaliNumber::localize((string) $row['total_marks']) }}
                            @endif
                        </td>
                        <td style="text-align: center;">{{ BengaliNumber::localize((string) ($row['best_marks'] ?? '-')) }}</td>
                        <td style="text-align: center; font-weight: bold;">
                            {{ $row['grade_label'] ?? '-' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary-heading">{{ __('Academic Transcript') }}</div>
        <table class="summary">
            <tr>
                <td class="summary-label">{{ __('Total Marks') }}</td>
                <td>{{ BengaliNumber::localize((string) $summary['total_marks']) }}</td>
                <td class="summary-label">{{ __('GPA') }}</td>
                <td>{{ BengaliNumber::localize((string) $summary['gpa']) }} ({{ $summary['overall_grade_label'] }})</td>
            </tr>
            <tr>
                <td class="summary-label">{{ __('Class Rank') }}</td>
                @if ($hasSections)
                    <td>{{ BengaliNumber::localize((string) ($summary['class_rank'] ?? '-')) }}</td>
                    <td class="summary-label">{{ __('Section Rank') }}</td>
                    <td>{{ BengaliNumber::localize((string) ($summary['section_rank'] ?? '-')) }}</td>
                @else
                    <td colspan="3">{{ BengaliNumber::localize((string) ($summary['class_rank'] ?? '-')) }}</td>
                @endif
            </tr>
            <tr>
                <td class="summary-label">{{ __('1st Position Total Marks') }}</td>
                <td>{{ BengaliNumber::localize((string) ($summary['top_rank_total_marks'] ?? '-')) }}</td>
                <td class="summary-label">{{ __('1st Position GPA') }}</td>
                <td>
                    @if ($summary['top_rank_gpa'])
                        {{ BengaliNumber::localize((string) $summary['top_rank_gpa']) }} ({{ $summary['top_rank_grade_label'] }})
                    @else
                        -
                    @endif
                </td>
            </tr>
            <tr>
                <td class="summary-label">{{ __('Working Days') }}</td>
                <td>{{ BengaliNumber::localize((string) $summary['working_days']) }}</td>
                <td class="summary-label">{{ __('Present') }}</td>
                <td>{{ BengaliNumber::localize((string) $summary['present_days']) }}</td>
            </tr>
        </table>

        <table class="signatures">
            <tr>
                <td>
                    <br><br><br>
                    {{ __('Class Teacher') }}
                </td>
                <td>
                    <br><br><br>
                    {{ __('Guardian') }}
                </td>
                <td>
                    @if ($sealDataUri)
                        <img src="{{ $sealDataUri }}" alt="Seal" width="50px"><br><br>
                    @else
                        <br><br>
                    @endif
                    {{ __('School Seal') }}
                </td>
                <td>
                    @if ($signatureDataUri)
                        <img src="{{ $signatureDataUri }}" alt="Signature" width="80px"><br><br>
                    @else
                        <br><br>
                    @endif
                    {{ __("Principal's Signature") }}
                </td>
            </tr>
        </table>

        @if ($footerText)
            <div class="footer">{{ $footerText }}</div>
        @endif
    </div>
</body>

</html>
