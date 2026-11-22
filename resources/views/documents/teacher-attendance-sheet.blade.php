@php
    use App\Models\SchoolSetting;
    use Carbon\Carbon;

    $schoolName = SchoolSetting::get('school_name', '');
    $schoolEiinLine = SchoolSetting::eiinLine();
    $schoolAddress = SchoolSetting::get('school_address', '');
    $schoolEstablishedYear = SchoolSetting::get('school_established_year', '');
    $schoolAddressLine = collect([
        $schoolAddress,
        $schoolEstablishedYear ? "Established: {$schoolEstablishedYear}" : null,
    ])->filter()->implode(' | ');

    $monthLabel = Carbon::create($year, $month, 1)->format('F Y');
    $markClasses = ['P' => 'present', 'LP' => 'late', 'A' => 'absent', 'L' => 'leave'];
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 10mm 6mm;
        }

        body {
            font-family: 'SolaimanLipi', sans-serif;
            font-size: 9px;
            color: #1a1a1a;
        }

        .header {
            text-align: center;
            border-bottom: 1px solid #1a1a1a;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }

        .school-name {
            font-size: 17px;
            font-weight: bold;
        }

        .school-address {
            font-size: 10px;
            color: #444;
        }

        .title {
            margin-top: 5px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        table.sheet {
            width: 100%;
            border-collapse: collapse;
        }

        table.sheet th,
        table.sheet td {
            border: 1px solid #999;
            text-align: center;
            padding: 3px 1px;
        }

        table.sheet th {
            background: #f2f2f2;
            font-weight: bold;
        }

        table.sheet th.sl,
        table.sheet td.sl {
            width: 20px;
        }

        table.sheet th.name,
        table.sheet td.name {
            width: 120px;
            text-align: left;
            padding-left: 4px;
        }

        .designation {
            font-size: 7px;
            color: #555;
        }

        .weekday {
            font-size: 6px;
            font-weight: normal;
            color: #555;
        }

        table.sheet .off {
            background: #e5e5e5;
        }

        table.sheet th.total,
        table.sheet td.total {
            width: 20px;
            background: #f8f8f8;
            font-weight: bold;
        }

        table.sheet td.present {
            color: #15803d;
            font-weight: bold;
        }

        table.sheet td.late {
            color: #b45309;
            font-weight: bold;
        }

        table.sheet td.absent {
            color: #b91c1c;
            font-weight: bold;
        }

        table.sheet td.leave {
            color: #1d4ed8;
            font-weight: bold;
        }

        .legend {
            margin-top: 6px;
            font-size: 9px;
            color: #444;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="school-name">{{ $schoolName }}</div>
        @if ($schoolEiinLine)
            <div class="school-address">{{ $schoolEiinLine }}</div>
        @endif
        @if ($schoolAddressLine)
            <div class="school-address">{{ $schoolAddressLine }}</div>
        @endif
        <div class="title">Teacher Attendance Sheet - {{ $monthLabel }}</div>
    </div>

    <table class="sheet">
        <thead>
            <tr>
                <th class="sl">SL</th>
                <th class="name">Teacher</th>
                @foreach ($days as $day)
                    <th class="{{ $day['is_working_day'] ? '' : 'off' }}">
                        {{ $day['day'] }}<br><span class="weekday">{{ $day['weekday'] }}</span>
                    </th>
                @endforeach
                <th class="total">P</th>
                <th class="total">LP</th>
                <th class="total">A</th>
                <th class="total">L</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="sl">{{ $loop->iteration }}</td>
                    <td class="name">
                        {{ $row['name'] }}
                        @if ($row['designation'])
                            <br><span class="designation">{{ $row['designation'] }}</span>
                        @endif
                    </td>
                    @foreach ($row['days'] as $day => $mark)
                        <td class="{{ $markClasses[$mark] ?? '' }} {{ $days[$day]['is_working_day'] ? '' : 'off' }}">
                            {{ $mark }}
                        </td>
                    @endforeach
                    <td class="total">{{ $row['totals']['P'] }}</td>
                    <td class="total">{{ $row['totals']['LP'] }}</td>
                    <td class="total">{{ $row['totals']['A'] }}</td>
                    <td class="total">{{ $row['totals']['L'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($days) + 6 }}">No active teachers.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="legend">P = Present, LP = Late Present, A = Absent, L = Leave. Shaded columns are weekends and holidays.</div>
</body>

</html>
