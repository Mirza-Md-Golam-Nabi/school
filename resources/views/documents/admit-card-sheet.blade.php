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

    $toDataUri = function (?string $path, string $disk = 'public'): ?string {
        if (!$path) {
            return null;
        }

        $storage = Storage::disk($disk);

        if (!$storage->exists($path)) {
            return null;
        }

        $mime = $storage->mimeType($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($storage->get($path));
    };

    // School seal/signature are the same for every card on the page, so
    // they're resolved once here instead of once per card.
    $sealDataUri = $toDataUri(SchoolSetting::get('school_seal'));
    $signatureDataUri = $toDataUri(SchoolSetting::get('principal_signature'));
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
                        $studentPhotoDataUri = $toDataUri($student->user?->avatar);
                    @endphp
                    <td class="cell">
                        <table class="card">
                            <tr>
                                <td class="card-inner">
                                    <table class="header-table">
                                        <tr>
                                            <td class="header-cell">
                                                <div class="school-name">{{ $schoolName }}</div>
                                                @if ($schoolAddressLine)
                                                    <div class="school-address">{{ $schoolAddressLine }}</div>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="title-cell">
                                                <table class="title">
                                                    <tr>
                                                        <td class="title-inner">Admit Card</td>
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
                                                        <td class="label">Name</td>
                                                        <td>{{ $student->user?->name }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="label">Class</td>
                                                        <td>{{ $student->class?->name }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="label">Roll No</td>
                                                        <td>{{ sprintf('%02d', $student->roll_no) }}</td>
                                                    </tr>
                                                    @if ($student->group || $student->section)
                                                        <tr>
                                                            <td class="label">Group</td>
                                                            <td>{{ $student->group?->name ?? "-" }}</td>
                                                            <td class="label">Section</td>
                                                            <td>{{ $student->section?->name ?? "-" }}</td>
                                                        </tr>
                                                    @endif
                                                    <tr>
                                                        <td class="label">Exam</td>
                                                        <td>{{ $exam->examType?->name }} -
                                                            {{ $exam->session_year }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="label">Period</td>
                                                        <td>{{ $exam->start_date?->format('d M') }} to
                                                            {{ $exam->end_date?->format('d M Y') }}</td>
                                                    </tr>
                                                </table>
                                            </td>
                                            <td class="photo-cell">
                                                @if ($studentPhotoDataUri)
                                                    <img class="photo" src="{{ $studentPhotoDataUri }}" alt="Photo"
                                                        width="42" height="52" style="width: 42px; height: 52px;">
                                                @endif
                                            </td>
                                        </tr>
                                    </table>

                                    <table class="signatures">
                                        <tr>
                                            <td>
                                                @if ($sealDataUri)
                                                    <img src="{{ $sealDataUri }}" alt="Seal" width="24" height="24"
                                                        style="width: 24px; height: 24px;"><br>
                                                @endif
                                                School Seal
                                            </td>
                                            <td>
                                                @if ($signatureDataUri)
                                                    <img src="{{ $signatureDataUri }}" alt="Signature" width="50"
                                                        height="20" style="width: 50px; height: 20px;"><br>
                                                @endif
                                                Principal's Signature
                                            </td>
                                        </tr>
                                    </table>
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
