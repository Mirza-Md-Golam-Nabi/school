@php
    use App\Models\SchoolSetting;
    use App\Support\PdfImage;

    $schoolName = SchoolSetting::get('school_name', '');
    $schoolEiinLine = SchoolSetting::eiinLine();
    $schoolAddress = SchoolSetting::get('school_address', '');
    $schoolEstablishedYear = SchoolSetting::get('school_established_year', '');
    $schoolAddressLine = collect([
        $schoolAddress,
        $schoolEstablishedYear ? "Established: {$schoolEstablishedYear}" : null,
    ])->filter()->implode(' | ');

    $formatMark = fn (?float $mark): string => $mark === null ? '-' : rtrim(rtrim(number_format($mark, 2), '0'), '.');

    $imageSrc = fn (?string $path): ?string => PdfImage::path($path);

    $sealSrc = $imageSrc(SchoolSetting::get('school_seal'));
    $signatureSrc = $imageSrc(SchoolSetting::get('principal_signature'));
    $classTeacherSignatureSrc = $imageSrc($exam->class?->classTeacher?->signature);

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
        /* The signatures live in the page footer, pinned to the bottom of
           every page; margin-bottom leaves room for it below the table. */
        @page {
            margin-top: 10mm;
            margin-left: 8mm;
            margin-right: 8mm;
            margin-bottom: 32mm;
            margin-footer: 6mm;
            footer: html_tabulationFooter;
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
    <htmlpagefooter name="tabulationFooter">
        <table class="signatures">
            <tr>
                <td>
                    @if ($classTeacherSignatureSrc)
                        <img src="{{ $classTeacherSignatureSrc }}" alt="Class Teacher Signature" width="80px"><br><br>
                    @else
                        <br><br><br>
                    @endif
                    Class Teacher
                </td>
                <td>
                    @if ($sealSrc)
                        <img src="{{ $sealSrc }}" alt="Seal" width="50px"><br><br>
                    @else
                        <br><br>
                    @endif
                    School Seal
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
    </htmlpagefooter>

    @foreach ($blocks as $index => $block)
        <div class="group-block {{ $index > 0 ? 'with-page-break' : '' }}">
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

            <div class="group-heading">{{ $block['group']->display_name }} {{ __('Group') }}</div>

            @php
                // A merged subject (e.g. Bangla 1st + 2nd paper) takes three columns: 1st | 2nd | merged.
                $blockHasMerges = $block['columns']->contains(fn (array $column): bool => count($column['papers']) === 2);
                $headRows = $blockHasMerges ? 2 : 1;
                $blockSubjectWidth = $subjectColumnWidth($block['columns']->sum(fn (array $column): int => count($column['papers']) === 2 ? 3 : 1));
            @endphp

            <table class="tabulation">
                <thead>
                    <tr>
                        <th class="roll-col" rowspan="{{ $headRows }}">Roll</th>
                        <th class="name-col" rowspan="{{ $headRows }}">Name</th>
                        @if ($hasSections)
                            <th class="section-col" rowspan="{{ $headRows }}">Section</th>
                        @endif
                        @foreach ($block['columns'] as $column)
                            @if (count($column['papers']) === 2)
                                <th class="subject-col" colspan="3">{{ $column['label'] }}</th>
                            @else
                                <th class="subject-col" rowspan="{{ $headRows }}" style="width: {{ $blockSubjectWidth }}%">{{ $column['label'] }}</th>
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
                    @if ($blockHasMerges)
                        <tr>
                            @foreach ($block['columns'] as $column)
                                @if (count($column['papers']) === 2)
                                    <th class="subject-col" style="width: {{ $blockSubjectWidth }}%">1st</th>
                                    <th class="subject-col" style="width: {{ $blockSubjectWidth }}%">2nd</th>
                                    <th class="subject-col" style="width: {{ $blockSubjectWidth }}%">Total</th>
                                @endif
                            @endforeach
                        </tr>
                    @endif
                </thead>
                <tbody>
                    @foreach ($block['rows'] as $row)
                        <tr>
                            <td class="roll-col">{{ sprintf('%02d', $row['roll_no']) }}</td>
                            <td class="name-col">{{ $row['name'] }}</td>
                            @if ($hasSections)
                                <td class="section-col">{{ $row['section_name'] ?? '-' }}</td>
                            @endif
                            @foreach ($block['columns'] as $column)
                                @foreach (count($column['papers']) === 2 ? [...$column['papers'], $column['key']] : $column['papers'] as $cellKey)
                                    <td class="subject-col" style="width: {{ $blockSubjectWidth }}%">
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
        </div>
    @endforeach
</body>

</html>
