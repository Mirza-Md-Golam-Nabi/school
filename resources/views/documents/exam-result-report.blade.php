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

    $imageSrc = fn (?string $path): ?string => PdfImage::path($path);

    $sealSrc = $imageSrc(SchoolSetting::get('school_seal'));
    $signatureSrc = $imageSrc(SchoolSetting::get('principal_signature'));
    $classTeacherSignatureSrc = $imageSrc($exam->class?->classTeacher?->signature);

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

        table.report .name-col {
            text-align: left;
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

    @php
        // One PDF, several pages; the signature footer (@page footer) prints on every page.
        $pages = [
            [
                'blocks' => [
                    ['title' => 'GPA Summary', 'labelHeading' => 'Result', 'rows' => $gradeRows, 'summaryRows' => [$totalRow, $absentRow, $examineeRow, $passedRow, $failedRow]],
                    ['title' => 'Failed Students by Number of Subjects', 'labelHeading' => 'Result', 'rows' => $failRows, 'summaryRows' => [$failedRow]],
                ],
                'showPassRate' => true,
            ],
            [
                'blocks' => [
                    ['title' => 'Subject-wise Failed Students', 'labelHeading' => 'Subject', 'rows' => $subjectRows, 'summaryRows' => [['label' => 'Total Failed Students'] + $failedRow]],
                ],
                'showPassRate' => false,
            ],
        ];

        // Then one page per passed-student list (per group / section), and one page with every failed student.
        foreach ($passedLists as $passedList) {
            $pages[] = ['blocks' => [], 'showPassRate' => false, 'studentBlocks' => [[
                'title' => 'Passed Students' . ($passedList['label'] ? " — {$breakdownLabel}: {$passedList['label']}" : '') . ' (' . count($passedList['students']) . ')',
                'students' => $passedList['students'],
                'isFailedList' => false,
            ]]];
        }

        $pages[] = ['blocks' => [], 'showPassRate' => false, 'studentBlocks' => $failedLists === []
            ? [['title' => 'Failed Students (0)', 'students' => [], 'isFailedList' => true]]
            : collect($failedLists)->map(fn (array $failedList): array => [
                'title' => "{$failedList['label']} (" . count($failedList['students']) . ')',
                'students' => $failedList['students'],
                'isFailedList' => true,
            ])->all(),
            'pageTitle' => 'Failed Students',
        ];

        $showSectionRank = $breakdownLabel === 'Section';
    @endphp

    @foreach ($pages as $page)
        @if (! $loop->first)
            <pagebreak />
        @endif

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
            <div class="subtitle">{{ $exam->examType?->name }} - {{ $exam->class?->display_name }} ({{ $exam->session_label }})</div>
        </div>

        @foreach ($page['blocks'] as $block)
            <div class="section-title">{{ $block['title'] }}</div>
            <table class="report">
                <thead>
                    <tr>
                        <th class="label-col">{{ $block['labelHeading'] }}</th>
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

        @if (isset($page['pageTitle']))
            <div class="section-title">{{ $page['pageTitle'] }}</div>
        @endif

        @foreach ($page['studentBlocks'] ?? [] as $studentBlock)
            <div class="section-title">{{ $studentBlock['title'] }}</div>
            <table class="report">
                <thead>
                    <tr>
                        <th>SL</th>
                        <th>Roll</th>
                        <th class="name-col">Name</th>
                        @if ($studentBlock['isFailedList'] && $breakdownLabel)
                            <th>{{ $breakdownLabel }}</th>
                        @endif
                        <th>GPA</th>
                        <th>Class Rank</th>
                        @if ($showSectionRank)
                            <th>Section Rank</th>
                        @endif
                        @if ($studentBlock['isFailedList'])
                            <th class="name-col">Failed Subjects</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($studentBlock['students'] as $student)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $student['roll'] ?? '—' }}</td>
                            <td class="name-col">{{ $student['name'] }}</td>
                            @if ($studentBlock['isFailedList'] && $breakdownLabel)
                                <td>{{ $student['breakdown'] ?? '—' }}</td>
                            @endif
                            <td>{{ number_format($student['gpa'], 2) }}</td>
                            <td>{{ $student['class_rank'] ?? '—' }}</td>
                            @if ($showSectionRank)
                                <td>{{ $student['section_rank'] ?? '—' }}</td>
                            @endif
                            @if ($studentBlock['isFailedList'])
                                <td class="name-col">{{ $student['failed_subjects'] }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">No students</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @endforeach

        @if ($page['showPassRate'])
            <div class="section-title">Pass Rate: {{ number_format($passRate, 2) }}%</div>
        @endif
    @endforeach
</body>

</html>
