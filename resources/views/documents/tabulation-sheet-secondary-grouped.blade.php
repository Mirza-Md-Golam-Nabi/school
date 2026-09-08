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

    // Fixed-width columns (roll/name/total/gpa/grade/rank, + section/sec-rank
    // when the class has sections) are reserved first; whatever percentage is
    // left is split evenly across however many subjects that group's own
    // block actually has — each group can offer a different subject count.
    $reservedColumnsPercent = 4 + ($hasSections ? 6 + 6 : 0) + 6 + 5 + 5 + 5 + 12;
    $subjectColumnWidth = fn (int $subjectCount): float => max((100 - $reservedColumnsPercent) / max($subjectCount, 1), 4);
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 10mm 8mm;
        }

        body {
            font-family: 'SolaimanLipi', sans-serif;
            font-size: 10px;
            color: #1a1a1a;
        }

        .header {
            text-align: center;
            border-bottom: 1px solid #1a1a1a;
            padding-bottom: 6px;
            margin-bottom: 6px;
        }

        .school-name {
            font-size: 17px;
            font-weight: bold;
        }

        .school-address {
            font-size: 10px;
            color: #444;
        }

        .exam-info {
            text-align: center;
            margin-bottom: 6px;
        }

        .title {
            margin-top: 6px;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .subtitle {
            margin-top: 2px;
            font-size: 10px;
            color: #444;
        }

        .group-block.with-page-break {
            page-break-before: always;
        }

        .group-heading {
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        table.tabulation {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        table.tabulation th,
        table.tabulation td {
            border: 1px solid #999;
            padding: 4px 5px;
            text-align: center;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        table.tabulation th {
            background: #f2f2f2;
        }

        table.tabulation th.roll-col,
        table.tabulation td.roll-col {
            width: 4%;
        }

        table.tabulation th.section-col,
        table.tabulation td.section-col {
            width: 6%;
        }

        table.tabulation th.total-col,
        table.tabulation td.total-col {
            width: 6%;
        }

        table.tabulation th.gpa-col,
        table.tabulation td.gpa-col {
            width: 5%;
        }

        table.tabulation th.grade-col,
        table.tabulation td.grade-col {
            width: 5%;
        }

        table.tabulation th.rank-col,
        table.tabulation td.rank-col {
            width: 5%;
        }

        table.tabulation th.sec-rank-col,
        table.tabulation td.sec-rank-col {
            width: 6%;
        }

        table.tabulation th.name-col,
        table.tabulation td.name-col {
            width: 12%;
            text-align: left;
        }

        .signatures {
            margin-top: 18px;
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .signatures td {
            text-align: center;
            font-size: 10px;
            width: 33.33%;
            vertical-align: bottom;
        }
    </style>
</head>

<body>
    @foreach ($blocks as $index => $block)
        <div class="group-block {{ $index > 0 ? 'with-page-break' : '' }}">
            <div class="header">
                <div class="school-name">{{ $schoolName }}</div>
                @if ($schoolAddressLine)
                    <div class="school-address">{{ $schoolAddressLine }}</div>
                @endif
            </div>
            <div class="exam-info">
                <div class="title">Tabulation Sheet</div>
                <div class="subtitle">{{ $exam->examType?->name }} - {{ $exam->class?->name }} ({{ $exam->session_year }})</div>
            </div>

            <div class="group-heading">{{ $block['group']->name }} Group</div>

            @php $blockSubjectWidth = $subjectColumnWidth($block['subjects']->count()); @endphp

            <table class="tabulation">
                <thead>
                    <tr>
                        <th class="roll-col">Roll</th>
                        <th class="name-col">Name</th>
                        @if ($hasSections)
                            <th class="section-col">Section</th>
                        @endif
                        @foreach ($block['subjects'] as $subject)
                            <th class="subject-col" style="width: {{ $blockSubjectWidth }}%">{{ $subject->name }}</th>
                        @endforeach
                        <th class="total-col">Total</th>
                        <th class="gpa-col">GPA</th>
                        <th class="grade-col">Grade</th>
                        <th class="rank-col">Rank</th>
                        @if ($hasSections)
                            <th class="sec-rank-col">Sec. Rank</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($block['rows'] as $row)
                        <tr>
                            <td class="roll-col">{{ sprintf('%02d', $row['roll_no']) }}</td>
                            <td class="name-col">{{ $row['name'] }}</td>
                            @if ($hasSections)
                                <td class="section-col">{{ $row['section_name'] ?? '-' }}</td>
                            @endif
                            @foreach ($block['subjects'] as $subject)
                                <td class="subject-col" style="width: {{ $blockSubjectWidth }}%">
                                    @if ($row['marks'][$subject->id]['is_absent'] ?? false)
                                        Absent
                                    @else
                                        {{ $formatMark($row['marks'][$subject->id]['value'] ?? null) }}
                                    @endif
                                </td>
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
        </div>
    @endforeach
</body>

</html>
