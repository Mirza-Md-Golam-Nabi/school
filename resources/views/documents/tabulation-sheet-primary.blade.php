@php
    use App\Models\SchoolSetting;
    use Illuminate\Support\Facades\Storage;

    $schoolName = SchoolSetting::get('school_name', '');
    $schoolEiinLine = SchoolSetting::eiinLine();
    $schoolAddress = SchoolSetting::get('school_address', '');
    $schoolEstablishedYear = SchoolSetting::get('school_established_year', '');
    $schoolAddressLine = collect([
        $schoolAddress,
        $schoolEstablishedYear ? "Established: {$schoolEstablishedYear}" : null,
    ])->filter()->implode(' | ');

    $formatMark = fn (?float $mark): string => $mark === null ? '-' : rtrim(rtrim(number_format($mark, 2), '0'), '.');

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
    $classTeacherSignatureDataUri = $toDataUri($exam->class?->classTeacher?->signature);

    // Fixed-width columns (roll/name/total/gpa/grade/rank, + section/sec-rank
    // when the class has sections) are reserved first; whatever percentage is
    // left is split evenly across however many subjects this exam actually
    // has, so the sheet stays balanced whether it's 4 subjects or 12.
    $reservedColumnsPercent = 6 + ($hasSections ? 8 + 8 : 0) + 8 + 7 + 7 + 7 + 14;
    // A merged subject (e.g. Bangla 1st + 2nd paper) takes three columns: 1st | 2nd | merged.
    $subjectCellCount = $columns->sum(fn (array $column): int => count($column['papers']) === 2 ? 3 : 1);
    $headRows = $hasMerges ? 2 : 1;
    $subjectColumnWidth = max((100 - $reservedColumnsPercent) / max($subjectCellCount, 1), 5);
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        /* The signatures live in the page footer, pinned to the bottom of
           every page; margin-bottom leaves room for it below the table. */
        @page {
            margin-top: 14mm;
            margin-left: 12mm;
            margin-right: 12mm;
            margin-bottom: 36mm;
            margin-footer: 8mm;
            footer: html_tabulationFooter;
        }

        body {
            font-family: 'SolaimanLipi', sans-serif;
            font-size: 11px;
            color: #1a1a1a;
        }

        .header {
            text-align: center;
            border-bottom: 1px solid #1a1a1a;
            padding-bottom: 8px;
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

        .exam-info {
            text-align: center;
            margin-bottom: 10px;
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

        table.tabulation {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        table.tabulation th,
        table.tabulation td {
            border: 1px solid #999;
            padding: 5px 6px;
            text-align: center;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        table.tabulation th {
            background: #f2f2f2;
        }

        table.tabulation th.roll-col,
        table.tabulation td.roll-col {
            width: 6%;
        }

        table.tabulation th.section-col,
        table.tabulation td.section-col {
            width: 9%;
        }

        table.tabulation th.total-col,
        table.tabulation td.total-col {
            width: 8%;
        }

        table.tabulation th.gpa-col,
        table.tabulation td.gpa-col {
            width: 7%;
        }

        table.tabulation th.grade-col,
        table.tabulation td.grade-col {
            width: 7%;
        }

        table.tabulation th.rank-col,
        table.tabulation td.rank-col {
            width: 7%;
        }

        table.tabulation th.sec-rank-col,
        table.tabulation td.sec-rank-col {
            width: 8%;
        }

        table.tabulation th.name-col,
        table.tabulation td.name-col {
            width: 14%;
            text-align: left;
        }

        .signatures {
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
    </style>
</head>

<body>
    <htmlpagefooter name="tabulationFooter">
        <table class="signatures">
            <tr>
                <td>
                    @if ($classTeacherSignatureDataUri)
                        <img src="{{ $classTeacherSignatureDataUri }}" alt="Class Teacher Signature" width="80px"><br><br>
                    @else
                        <br><br><br>
                    @endif
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
                    {{ SchoolSetting::headSignatoryTitle() }}
                </td>
            </tr>
        </table>
    </htmlpagefooter>

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
        <div class="title">Tabulation Sheet</div>
        <div class="subtitle">{{ $exam->examType?->name }} - {{ $exam->class?->display_name }} ({{ $exam->session_year }})</div>
    </div>

    <table class="tabulation">
        <thead>
            <tr>
                <th class="roll-col" rowspan="{{ $headRows }}">Roll</th>
                <th class="name-col" rowspan="{{ $headRows }}">Name</th>
                @if ($hasSections)
                    <th class="section-col" rowspan="{{ $headRows }}">Section</th>
                @endif
                @foreach ($columns as $column)
                    @if (count($column['papers']) === 2)
                        <th class="subject-col" colspan="3">{{ $column['label'] }}</th>
                    @else
                        <th class="subject-col" rowspan="{{ $headRows }}" style="width: {{ $subjectColumnWidth }}%">{{ $column['label'] }}</th>
                    @endif
                @endforeach
                <th class="total-col" rowspan="{{ $headRows }}">Total</th>
                <th class="gpa-col" rowspan="{{ $headRows }}">GPA</th>
                <th class="grade-col" rowspan="{{ $headRows }}">Grade</th>
                <th class="rank-col" rowspan="{{ $headRows }}">Rank</th>
                @if ($hasSections)
                    <th class="sec-rank-col" rowspan="{{ $headRows }}">Sec. Rank</th>
                @endif
            </tr>
            @if ($hasMerges)
                <tr>
                    @foreach ($columns as $column)
                        @if (count($column['papers']) === 2)
                            <th class="subject-col" style="width: {{ $subjectColumnWidth }}%">1st</th>
                            <th class="subject-col" style="width: {{ $subjectColumnWidth }}%">2nd</th>
                            <th class="subject-col" style="width: {{ $subjectColumnWidth }}%">Total</th>
                        @endif
                    @endforeach
                </tr>
            @endif
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td class="roll-col">{{ sprintf('%02d', $row['roll_no']) }}</td>
                    <td class="name-col">{{ $row['name'] }}</td>
                    @if ($hasSections)
                        <td class="section-col">{{ $row['section_name'] ?? '-' }}</td>
                    @endif
                    @foreach ($columns as $column)
                        @foreach (count($column['papers']) === 2 ? [...$column['papers'], $column['key']] : $column['papers'] as $cellKey)
                            <td class="subject-col" style="width: {{ $subjectColumnWidth }}%">
                                @if ($row['marks'][$cellKey]['is_absent'] ?? false)
                                    Absent
                                @else
                                    {{ $formatMark($row['marks'][$cellKey]['value'] ?? null) }}
                                @endif
                            </td>
                        @endforeach
                    @endforeach
                    <td class="total-col">{{ $formatMark($row['total_marks']) }}</td>
                    <td class="gpa-col">{{ number_format($row['gpa'], 2) }}</td>
                    <td class="grade-col">{{ $row['grade_label'] ?? '-' }}</td>
                    <td class="rank-col">{{ $row['class_rank'] ?? '-' }}</td>
                    @if ($hasSections)
                        <td class="sec-rank-col">{{ $row['section_rank'] ?? '-' }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>
