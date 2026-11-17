@php
    use App\Models\SchoolSetting;
    use App\Support\BengaliNumber;
    use App\Support\DocumentLocale;
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

    // School seal/signature are the same for every card on the page, so
    // they're resolved once here instead of once per card.
    $sealSrc = $imageSrc(SchoolSetting::get('school_seal'));
    $signatureSrc = $imageSrc(SchoolSetting::get('principal_signature'));
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 8mm;
        }

        body {
            font-family: 'SolaimanLipi', sans-serif;
            font-size: 12px;
            color: #1a1a1a;
        }

        table.sheet {
            width: 100%;
            border-collapse: collapse;
        }

        table.sheet td.cell {
            width: 50%;
            padding: 3mm;
            vertical-align: top;
            border: 1px dashed #aaaaaa;
        }

        table.card {
            width: 100%;
            border-collapse: collapse;
        }

        table.card td.card-inner {
            border: 1.5px solid #1a1a1a;
            padding: 6px;
        }

        table.header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        table.header-table td.header-cell {
            text-align: center;
            border-bottom: 1px solid #1a1a1a;
            padding-bottom: 4px;
        }

        table.header-table td.title-cell {
            text-align: center;
            padding-top: 4px;
            padding-bottom: 2px;
        }

        .header-cell img.logo {
            margin-bottom: 2px;
        }

        .school-name {
            font-size: 14px;
            font-weight: bold;
        }

        .school-address {
            font-size: 10px;
            color: #444;
        }

        table.title {
            border-collapse: collapse;
        }

        table.title td.title-inner {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 4px 15px;
            border: 1px solid #1a1a1a;
            white-space: nowrap;
        }

        table.body {
            width: 100%;
            border-collapse: collapse;
        }

        table.body td.info {
            vertical-align: top;
        }

        table.body td.photo-cell {
            width: 48px;
            vertical-align: top;
            text-align: center;
        }

        .photo {
            border: 1px solid #1a1a1a;
        }

        table.info-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.info-table td {
            padding: 1.5px 3px;
            vertical-align: top;
            font-size: 10px;
        }

        table.info-table td.label {
            font-weight: bold;
            width: 55px;
        }

        table.signatures {
            width: 100%;
            margin-top: 6px;
        }

        table.signatures td {
            text-align: center;
            font-size: 10px;
        }

        .powered-by {
            margin-top: 4px;
            text-align: center;
            font-size: 8px;
            color: #777777;
        }
    </style>
</head>

<body>
    <table class="sheet">
        @foreach ($admitCards->chunk(2) as $pair)
            <tr>
                @foreach ($pair as $admitCard)
                    @php
                        $student = $admitCard->student;
                        $exam = $admitCard->exam;
                        $studentPhotoSrc = $imageSrc($student->photo);

                        // The card's title and student details, in its student_info_language.
                        $info = DocumentLocale::using($admitCard->student_info_language, fn (): array => [
                            'title' => __('Admit Card'),
                            'labels' => collect(['Student ID', 'Name', 'Class', 'Roll No', 'Group', 'Section', 'Exam', 'Period'])
                                ->mapWithKeys(fn (string $label): array => [$label => __($label)]),
                            'studentId' => BengaliNumber::localize((string) $student->idCardNumber()),
                            'className' => $student->class?->display_name,
                            'rollNo' => BengaliNumber::localize(sprintf('%02d', $student->roll_no)),
                            'groupName' => $student->group?->display_name,
                            'exam' => $exam->examType?->name.' - '.BengaliNumber::localize((string) $exam->session_year),
                            'period' => __(':start to :end', [
                                'start' => BengaliNumber::localize((string) $exam->start_date?->translatedFormat('d M')),
                                'end' => BengaliNumber::localize((string) $exam->end_date?->translatedFormat('d M Y')),
                            ]),
                        ]);
                        $label = $info['labels'];
                    @endphp
                    <td class="cell">
                        <table class="card">
                            <tr>
                                <td class="card-inner">
                                    <table class="header-table">
                                        <tr>
                                            <td class="header-cell">
                                                <div class="school-name">{{ $schoolName }}</div>
                                                @if ($schoolEiinLine)
                                                    <div class="school-address">{{ $schoolEiinLine }}</div>
                                                @endif
                                                @if ($schoolAddressLine)
                                                    <div class="school-address">{{ $schoolAddressLine }}</div>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="title-cell">
                                                <table class="title">
                                                    <tr>
                                                        <td class="title-inner">{{ $info['title'] }}</td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>

                                    <table class="body">
                                        <tr>
                                            <td class="info">
                                                <table class="info-table">
                                                    <tr>
                                                        <td class="label">{{ $label['Student ID'] }}</td>
                                                        <td>{{ $info['studentId'] }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="label">{{ $label['Name'] }}</td>
                                                        <td>{{ $student->user?->name }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="label">{{ $label['Class'] }}</td>
                                                        <td>{{ $info['className'] }}</td>
                                                        <td class="label">{{ $label['Roll No'] }}</td>
                                                        <td>{{ $info['rollNo'] }}</td>
                                                    </tr>
                                                    @if ($student->group || $student->section)
                                                        <tr>
                                                            @if ($student->group)
                                                                <td class="label">{{ $label['Group'] }}</td>
                                                                <td>{{ $info['groupName'] }}</td>
                                                            @endif
                                                            @if ($student->section)
                                                                <td class="label">{{ $label['Section'] }}</td>
                                                                <td>{{ $student->section->name }}</td>
                                                            @endif
                                                        </tr>
                                                    @endif
                                                    <tr>
                                                        <td class="label">{{ $label['Exam'] }}</td>
                                                        <td>{{ $info['exam'] }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="label">{{ $label['Period'] }}</td>
                                                        <td>{{ $info['period'] }}</td>
                                                    </tr>
                                                </table>
                                            </td>
                                            <td class="photo-cell">
                                                @if ($studentPhotoSrc)
                                                    <img class="photo" src="{{ $studentPhotoSrc }}" alt="Photo"
                                                        width="42" height="52" style="width: 42px; height: 52px;">
                                                @endif
                                            </td>
                                        </tr>
                                    </table>

                                    <table class="signatures">
                                        <tr>
                                            <td>
                                                @if ($sealSrc)
                                                    <img src="{{ $sealSrc }}" alt="Seal" width="24" height="24"
                                                        style="width: 24px; height: 24px;"><br>
                                                @endif
                                                School Seal
                                            </td>
                                            <td>
                                                @if ($signatureSrc)
                                                    <img src="{{ $signatureSrc }}" alt="Signature" width="50"
                                                        height="20" style="width: 50px; height: 20px;"><br>
                                                @endif
                                                {{ SchoolSetting::headSignatoryTitle() }}
                                            </td>
                                        </tr>
                                    </table>
                                    <div class="powered-by">Powered by: Next Fusion</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                @endforeach
                @if ($pair->count() === 1)
                    <td class="cell"></td>
                @endif
            </tr>
        @endforeach
    </table>
</body>

</html>
