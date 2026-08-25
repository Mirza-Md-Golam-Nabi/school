@php
    use App\Models\SchoolSetting;
    use Carbon\Carbon;

    $schoolName = SchoolSetting::get('school_name', '');
    $schoolAddress = SchoolSetting::get('school_address', '');
    $schoolEstablishedYear = SchoolSetting::get('school_established_year', '');
    $schoolAddressLine = collect([
        $schoolAddress,
        $schoolEstablishedYear ? "Established: {$schoolEstablishedYear}" : null,
    ])->filter()->implode(' | ');

    $monthLabel = Carbon::create($year, $month, 1)->format('F Y');
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 12mm 8mm;
        }

        body {
            font-family: 'SolaimanLipi', sans-serif;
            font-size: 10px;
            color: #1a1a1a;
        }

        .header {
            text-align: center;
            border-bottom: 1px solid #1a1a1a;
            padding-bottom: 8px;
            margin-bottom: 10px;
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
            margin-top: 6px;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
        }

        table.report th,
        table.report td {
            border: 1px solid #999;
            text-align: center;
            padding: 3px 2px;
        }

        table.report th.roll,
        table.report td.roll {
            width: 28px;
        }

        table.report th.name,
        table.report td.name {
            width: 120px;
            text-align: left;
            padding-left: 4px;
        }

        table.report th {
            background: #f2f2f2;
            font-weight: bold;
        }

        table.report td.present {
            color: #15803d;
            font-weight: bold;
        }

        table.report td.absent {
            color: #b91c1c;
            font-weight: bold;
        }

        .legend {
            margin-top: 8px;
            font-size: 10px;
            color: #444;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="school-name">{{ $schoolName }}</div>
        @if ($schoolAddressLine)
            <div class="school-address">{{ $schoolAddressLine }}</div>
        @endif
        <div class="title">Student Attendance Report - {{ $class->name }} - {{ $monthLabel }}</div>
    </div>

    <table class="report">
        <thead>
            <tr>
                <th class="roll">Roll</th>
                <th class="name">Name</th>
                @for ($day = 1; $day <= $daysInMonth; $day++)
                    <th>{{ $day }}</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td class="roll">{{ sprintf('%02d', $row['roll_no']) }}</td>
                    <td class="name">{{ $row['name'] }}</td>
                    @foreach ($row['days'] as $mark)
                        <td class="{{ $mark === 'P' ? 'present' : ($mark === 'A' ? 'absent' : '') }}">
                            {{ $mark }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="legend">P = Present, A = Absent</div>
</body>

</html>
