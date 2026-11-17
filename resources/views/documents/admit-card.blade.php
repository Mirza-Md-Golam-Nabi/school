@php
    use App\Models\SchoolSetting;
    use App\Support\BengaliNumber;
    use App\Support\DocumentLocale;
    use App\Support\PdfImage;

    $student = $admitCard->student;
    $exam = $admitCard->exam;

    $schoolName = SchoolSetting::get('school_name', '');
    $schoolEiinLine = SchoolSetting::eiinLine();
    $schoolAddress = SchoolSetting::get('school_address', '');
    $schoolEstablishedYear = SchoolSetting::get('school_established_year', '');
    $schoolAddressLine = collect([
        $schoolAddress,
        $schoolEstablishedYear ? "Established: {$schoolEstablishedYear}" : null,
    ])->filter()->implode(' | ');
    $footerText = SchoolSetting::get('admit_card_footer_text', '');

    $imageSrc = fn (?string $path): ?string => PdfImage::path($path);

    $sealSrc = $imageSrc(SchoolSetting::get('school_seal'));
    $signatureSrc = $imageSrc(SchoolSetting::get('principal_signature'));
    $studentPhotoSrc = $imageSrc($student->photo);

    // The card's title and student details, in the card's student_info_language.
    $info = DocumentLocale::using($admitCard->student_info_language, fn (): array => [
        'title' => __('Admit Card'),
        'labels' => collect(['Student ID', 'Student Name', 'Class', 'Roll No', 'Group', 'Section', 'Exam', 'Exam Period'])
            ->mapWithKeys(fn (string $label): array => [$label => __($label)]),
        'studentId' => BengaliNumber::localize((string) $student->idCardNumber()),
        'className' => $student->class?->display_name,
        'rollNo' => BengaliNumber::localize(sprintf('%02d', $student->roll_no)),
        'groupName' => $student->group?->display_name,
        'exam' => $exam->examType?->name.' - '.BengaliNumber::localize((string) $exam->session_year),
        'period' => __(':start to :end', [
            'start' => BengaliNumber::localize((string) $exam->start_date?->translatedFormat('d M Y')),
            'end' => BengaliNumber::localize((string) $exam->end_date?->translatedFormat('d M Y')),
        ]),
    ]);
    $label = $info['labels'];
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

    </style>
</head>

<body>
    <div class="card">
        <div class="header">
            <div class="school-name">{{ $schoolName }}</div>
            @if ($schoolEiinLine)
                <div class="school-address">{{ $schoolEiinLine }}</div>
            @endif
            @if ($schoolAddressLine)
                <div class="school-address">{{ $schoolAddressLine }}</div>
            @endif
        </div>

        <div class="title">{{ $info['title'] }}</div>

        <table class="info">
            <tr>
                <td class="label">{{ $label['Student ID'] }}</td>
                <td colspan="3">{{ $info['studentId'] }}</td>
            </tr>
            <tr>
                <td class="label">{{ $label['Student Name'] }}</td>
                <td colspan="3">{{ $student->user?->name }}</td>
            </tr>
            <tr>
                <td class="label">{{ $label['Class'] }}</td>
                <td>{{ $info['className'] }}</td>
                <td class="label">{{ $label['Roll No'] }}</td>
                <td>{{ $info['rollNo'] }}</td>
            </tr>
            @if ($student->group && $student->section)
                <tr>
                    <td class="label">{{ $label['Group'] }}</td>
                    <td>{{ $info['groupName'] }}</td>
                    <td class="label">{{ $label['Section'] }}</td>
                    <td>{{ $student->section?->name }}</td>
                </tr>
            @elseif($student->group)
                <tr>
                    <td class="label">{{ $label['Group'] }}</td>
                    <td colspan="3">{{ $info['groupName'] }}</td>
                </tr>
            @elseif($student->section)
                <tr>
                    <td class="label">{{ $label['Section'] }}</td>
                    <td colspan="3">{{ $student->section?->name }}</td>
                </tr>
            @endif
            <tr>
                <td class="label">{{ $label['Exam'] }}</td>
                <td colspan="3">{{ $info['exam'] }}</td>
            </tr>
            <tr>
                <td class="label">{{ $label['Exam Period'] }}</td>
                <td colspan="3">{{ $info['period'] }}</td>
            </tr>
        </table>

        <table class="signatures">
            <tr>
                <td>
                    @if ($sealSrc)
                        <img src="{{ $sealSrc }}" alt="Seal" width="50px"><br><br>
                    @endif
                    School Seal
                </td>
                <td>
                    @if ($signatureSrc)
                        <img src="{{ $signatureSrc }}" alt="Signature" width="80px"><br>
                    @endif
                    {{ SchoolSetting::headSignatoryTitle() }}
                </td>
            </tr>
        </table>

        <div class="footer">
            @if ($footerText)
                {{ $footerText }}<br>
            @endif
            Powered by: Next Fusion
        </div>
    </div>
</body>

</html>
