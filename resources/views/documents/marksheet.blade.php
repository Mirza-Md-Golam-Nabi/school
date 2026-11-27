@php
    use App\Models\GradeScale;
    use App\Models\Marksheet;
    use App\Models\SchoolSetting;
    use App\Support\PdfImage;
    use App\Support\BengaliNumber;

    $student = $marksheet->student;
    $exam = $marksheet->exam;
    $hasSections ??= $exam->class?->sections()->exists() ?? false;

    $gradeScales = GradeScale::cached();
    $formatMark = fn (float $mark): string => rtrim(rtrim(number_format($mark, 2, '.', ''), '0'), '.');

    $schoolName = SchoolSetting::get('school_name', '');
    $schoolEiinLine = SchoolSetting::eiinLine();
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

    $imageSrc = fn (?string $path): ?string => PdfImage::path($path);

    // The logo sits left of the school name and the verification QR right of
    // it, in side cells of equal width, so the name stays centred on the page.
    $verificationUrl = Marksheet::showsQrCode() ? $marksheet->verificationUrl() : null;

    $logoSrc = $useLogo ? $imageSrc(SchoolSetting::get('school_logo')) : null;
    $studentPhotoSrc = $imageSrc($student->photo);
    $sealSrc = $imageSrc(SchoolSetting::get('school_seal'));
    $signatureSrc = $imageSrc(SchoolSetting::get('principal_signature'));
    $classTeacherSignatureSrc = $imageSrc($exam->class?->classTeacher?->signature);

    // A merged subject (e.g. Bangla 1st + 2nd paper) prints one line per paper.
    $markLines = $rows->flatMap(fn (array $row): array => $row['papers'] ?? [$row]);

    // A result made by a result rule gets a column for what each of its exams gave.
    $componentNames = $markLines->flatMap(fn (array $line): array => array_column($line['components'], 'name'))->unique()->values();

    // The practical column only when some subject has practical marks.
    $hasPractical = $markLines->contains(fn (array $line): bool => $line['practical_marks'] !== null);
    $hasMergedSubjects = $rows->contains(fn (array $row): bool => $row['papers'] !== null);
    $subjectColspan = $hasMergedSubjects ? 2 : 1;

    // Fixed widths keep the "1st / 2nd" paper column narrow so the marks columns get the room.
    $markColumnCount = 6 + ($hasPractical ? 1 : 0) + $componentNames->count() + ($hasMergedSubjects ? 1 : 0);
    $paperColumnWidth = $hasMergedSubjects ? 6 : 0;
    $markColumnWidth = round(min(8, 66 / $markColumnCount), 2);
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        /* The signatures live in the page footer, pinned to the bottom of the
           page; margin-bottom leaves room for it inside the page border. */
        @page {
            margin-top: 12mm;
            margin-left: 12mm;
            margin-right: 12mm;
            margin-bottom: 45mm;
            margin-footer: 11mm;
            footer: html_marksheetFooter;
        }

        body {
            font-family: 'SolaimanLipi', sans-serif;
            font-size: 12px;
            color: #1a1a1a;
        }

        /* Absolute blocks are placed against the whole A4 page (210 x 297mm),
           so the border always reaches the bottom of the page. */
        .page-border {
            position: absolute;
            top: 7mm;
            left: 7mm;
            width: 196mm;
            height: 283mm;
            border: 2px solid #1a1a1a;
        }

        .header {
            text-align: center;
            border-bottom: 1px solid #1a1a1a;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .header img.logo {
            height: 60px;
        }

        table.header-layout {
            width: 100%;
            border-collapse: collapse;
        }

        table.header-layout td {
            vertical-align: middle;
            text-align: center;
            padding: 0;
        }

        table.header-layout td.header-side {
            width: 34mm;
        }

        .qr-caption {
            font-size: 8px;
            color: #444;
        }

        .qr-link,
        .qr-code {
            font-size: 7px;
            color: #555;
        }

        .qr-code {
            font-weight: bold;
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

        td.photo-cell {
            width: 84px;
            vertical-align: top;
            text-align: right;
        }

        .photo {
            border: 1px solid #1a1a1a;
            padding: 2px;
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

    <div class="page-border"></div>

    <htmlpagefooter name="marksheetFooter">
        <table class="signatures">
            <tr>
                <td>
                    @if ($classTeacherSignatureSrc)
                        <img src="{{ $classTeacherSignatureSrc }}" alt="Class Teacher Signature" width="80px"><br><br>
                    @else
                        <br><br><br>
                    @endif
                    {{ __('Class Teacher') }}
                </td>
                <td>
                    <br><br><br>
                    {{ __('Guardian') }}
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
    </htmlpagefooter>

    <div class="sheet">
        <div class="header">
            <table class="header-layout">
                <tr>
                    <td class="header-side">
                        @if ($logoSrc)
                            <img class="logo" src="{{ $logoSrc }}" alt="Logo">
                        @endif
                    </td>
                    <td>
                        <div class="school-name">{{ $schoolName }}</div>
                        @if ($schoolEiinLine)
                            <div class="school-address">{{ $schoolEiinLine }}</div>
                        @endif
                        @if ($schoolAddressLine)
                            <div class="school-address">{{ $schoolAddressLine }}</div>
                        @endif
                    </td>
                    <td class="header-side">
                        @if ($verificationUrl)
                            @include('documents.partials.marksheet-qr', ['verificationUrl' => $verificationUrl, 'marksheet' => $marksheet])
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <div class="title">{{ __('Marksheet') }} - {{ $exam->examType?->name }} {{ BengaliNumber::localize($exam->session_label) }}</div>

        <table class="top-section">
            <tr>
                <td class="info-col">
                    {{-- The student's photo, when the school uploaded one, beside their details. --}}
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="vertical-align: top; padding: 0;">
                    <table class="info">
                        <tr>
                            <td class="label">{{ __('Student ID') }}</td>
                            <td>{{ BengaliNumber::localize($student->idCardNumber()) }}</td>
                        </tr>
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
                            <td colspan="3">{{ BengaliNumber::localize($student->session_label) }}</td>
                        </tr>
                    </table>
                            </td>
                            @if ($studentPhotoSrc)
                                <td class="photo-cell">
                                    <img class="photo" src="{{ $studentPhotoSrc }}" alt="Photo" width="74" height="88" style="width: 74px; height: 88px;">
                                </td>
                            @endif
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
                    <th style="text-align: left;" colspan="{{ $subjectColspan }}">{{ __('Subject') }}</th>
                    <th style="width: {{ $markColumnWidth }}%;">{{ __('MCQ') }}</th>
                    <th style="width: {{ $markColumnWidth }}%;">{{ __('Written') }}</th>
                    @if ($hasPractical)
                        <th style="width: {{ $markColumnWidth }}%;">{{ __('Practical') }}</th>
                    @endif
                    @foreach ($componentNames as $componentName)
                        <th style="width: {{ $markColumnWidth }}%;">{{ $componentName }}</th>
                    @endforeach
                    <th style="width: {{ $markColumnWidth }}%;">{{ __('Marks') }}</th>
                    @if ($hasMergedSubjects)
                        <th style="width: {{ $markColumnWidth }}%;">{{ __('Total') }}</th>
                    @endif
                    <th style="width: {{ $markColumnWidth }}%;">{{ __('Best') }}</th>
                    <th style="width: {{ $markColumnWidth }}%;">{{ __('Grade') }}</th>
                    <th style="width: {{ $markColumnWidth }}%;">{{ __('GP') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php
                        $isMerged = $row['papers'] !== null;
                        $lines = $row['papers'] ?? [$row];
                        $lineCount = count($lines);
                    @endphp
                    @foreach ($lines as $lineIndex => $line)
                        <tr>
                            @if ($lineIndex === 0)
                                <td rowspan="{{ $lineCount }}" colspan="{{ $isMerged ? 1 : $subjectColspan }}">{{ $row['subject_name'] }}</td>
                            @endif
                            @if ($isMerged)
                                <td style="text-align: center; width: {{ $paperColumnWidth }}%;">{{ $line['paper_label'] }}</td>
                            @endif
                            {{-- Absent in only the theory or the practical exam: that part shows Absent. --}}
                            @php $isPartlyAbsent = ! $line['is_absent'] && (($line['is_theory_absent'] ?? false) || ($line['is_practical_absent'] ?? false)); @endphp
                            <td style="text-align: center;">{{ $isPartlyAbsent && $line['is_theory_absent'] ? __('Absent') : BengaliNumber::localize((string) ($line['mcq_marks'] ?? '-')) }}</td>
                            <td style="text-align: center;">{{ $isPartlyAbsent && $line['is_theory_absent'] ? __('Absent') : BengaliNumber::localize((string) ($line['written_marks'] ?? '-')) }}</td>
                            @if ($hasPractical)
                                <td style="text-align: center;">{{ $isPartlyAbsent && $line['is_practical_absent'] ? __('Absent') : BengaliNumber::localize((string) ($line['practical_marks'] ?? '-')) }}</td>
                            @endif
                            @foreach ($componentNames as $componentName)
                                @php $component = collect($line['components'])->firstWhere('name', $componentName); @endphp
                                <td style="text-align: center;">
                                    @if (! $component)
                                        -
                                    @elseif ($component['is_absent'])
                                        {{ __('Absent') }}
                                    @else
                                        {{ BengaliNumber::localize($formatMark($component['marks'])) }}
                                    @endif
                                </td>
                            @endforeach
                            {{-- An unmerged subject's marks are its total, so the two cells are one. --}}
                            <td style="text-align: center;" colspan="{{ ! $isMerged && $hasMergedSubjects ? 2 : 1 }}">
                                @if ($line['is_absent'])
                                    {{ __('Absent') }}
                                @elseif ($line['total_marks'] === null)
                                    -
                                @else
                                    {{ BengaliNumber::localize($formatMark((float) $line['total_marks'])) }}
                                @endif
                            </td>
                            @if ($lineIndex === 0)
                                @if ($isMerged)
                                    <td style="text-align: center; font-weight: bold;" rowspan="{{ $lineCount }}">
                                        @if ($row['is_absent'])
                                            {{ __('Absent') }}
                                        @else
                                            {{ BengaliNumber::localize($formatMark((float) $row['total_marks'])) }}
                                        @endif
                                    </td>
                                @endif
                                <td style="text-align: center;" rowspan="{{ $lineCount }}">{{ BengaliNumber::localize($row['best_marks'] === null ? '-' : $formatMark((float) $row['best_marks'])) }}</td>
                                <td style="text-align: center; font-weight: bold;" rowspan="{{ $lineCount }}">
                                    {{ $row['grade_label'] ?? '-' }}
                                </td>
                                <td style="text-align: center;" rowspan="{{ $lineCount }}">{{ BengaliNumber::localize($row['grade_point'] === null ? '-' : number_format((float) $row['grade_point'], 2)) }}</td>
                            @endif
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>

        <div class="summary-heading">{{ __('Academic Transcript') }}</div>
        <table class="summary">
            <tr>
                <td class="summary-label">{{ __('Obtained Marks') }}</td>
                <td>{{ __(':obtained out of :full', [
                    'obtained' => BengaliNumber::localize($formatMark((float) $summary['obtained_marks'])),
                    'full' => BengaliNumber::localize($formatMark((float) $summary['obtained_full_marks'])),
                ]) }}</td>
                <td class="summary-label">{{ __('GPA') }}</td>
                <td>{{ BengaliNumber::localize((string) $summary['gpa']) }} ({{ $summary['overall_grade_label'] }})</td>
            </tr>
            <tr>
                <td class="summary-label">{{ __('Class Rank') }}</td>
                <td>{{ BengaliNumber::localize((string) ($summary['class_rank'] ?? '-')) }}</td>
                <td class="summary-label">{{ __('Total GP') }}</td>
                <td>{{ BengaliNumber::localize((string) ($summary['total_grade_point'] ?? '-')) }}</td>
            </tr>
            @if ($hasSections)
                <tr>
                    <td class="summary-label">{{ __('Section Rank') }}</td>
                    <td colspan="3">{{ BengaliNumber::localize((string) ($summary['section_rank'] ?? '-')) }}</td>
                </tr>
            @endif
            <tr>
                <td class="summary-label">{{ __('1st Position Obtained Marks') }}</td>
                <td>{{ $summary['top_rank_obtained_marks'] === null ? '-' : BengaliNumber::localize($formatMark((float) $summary['top_rank_obtained_marks'])) }}</td>
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

        @if ($footerText)
            <div class="footer">{{ $footerText }}</div>
        @endif
    </div>
</body>

</html>
