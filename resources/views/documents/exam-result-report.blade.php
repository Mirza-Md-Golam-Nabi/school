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

    $columnCount = count($columns);
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin-top: 14mm;
            margin-left: 14mm;
            margin-right: 14mm;
            margin-bottom: 36mm;
            margin-footer: 8mm;
            footer: html_reportFooter;
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
            margin-bottom: 12px;
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

        .section-title {
            margin: 14px 0 6px;
            font-size: 13px;
            font-weight: bold;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
        }

        table.report th,
        table.report td {
            border: 1px solid #999;
            padding: 6px 8px;
            text-align: center;
        }

        table.report th {
            background: #f2f2f2;
        }

        table.report .label-col {
            text-align: left;
            width: 40%;
        }

        table.report tr.summary td {
            font-weight: bold;
            background: #fafafa;
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
    <htmlpagefooter name="reportFooter">
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
        <div class="title">Exam Result Report</div>
        <div class="subtitle">{{ $exam->examType?->name }} - {{ $exam->class?->display_name }} ({{ $exam->session_year }})</div>
    </div>

    @foreach ([
        ['title' => 'GPA Summary', 'rows' => $gradeRows, 'summaryRows' => [$totalRow, $passedRow, $failedRow]],
        ['title' => 'Failed Students by Number of Subjects', 'rows' => $failRows, 'summaryRows' => [$failedRow]],
    ] as $block)
        <div class="section-title">{{ $block['title'] }}</div>
        <table class="report">
            <thead>
                <tr>
                    <th class="label-col">Result</th>
                    @foreach ($columns as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                    <th>{{ $columnCount ? 'Total' : 'Students' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($block['rows'] as $row)
                    <tr>
                        <td class="label-col">{{ $row['label'] }}</td>
                        @foreach ($columns as $column)
                            <td>{{ $row['by'][$column['key']] ?? 0 }}</td>
                        @endforeach
                        <td>{{ $row['total'] }}</td>
                    </tr>
                @endforeach
                @foreach ($block['summaryRows'] as $row)
                    <tr class="summary">
                        <td class="label-col">{{ $row['label'] }}</td>
                        @foreach ($columns as $column)
                            <td>{{ $row['by'][$column['key']] ?? 0 }}</td>
                        @endforeach
                        <td>{{ $row['total'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <div class="section-title">Pass Rate: {{ number_format($passRate, 2) }}%</div>
</body>

</html>
