@php
    use App\Enums\AttendanceStatus;
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

    $marks = [
        AttendanceStatus::Present->value => ['P', 'present'],
        AttendanceStatus::Late->value => ['LP', 'late'],
        AttendanceStatus::Absent->value => ['A', 'absent'],
        AttendanceStatus::Leave->value => ['L', 'leave'],
    ];
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 12mm 12mm;
        }

        body {
            font-family: 'SolaimanLipi', sans-serif;
            font-size: 11px;
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

        table.overview {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        table.overview td.box {
            width: 50%;
            vertical-align: top;
            border: 1px solid #999;
            padding: 6px 8px;
        }

        .box-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            color: #444;
            border-bottom: 1px solid #ccc;
            padding-bottom: 3px;
            margin-bottom: 4px;
        }

        table.facts {
            width: 100%;
            border-collapse: collapse;
        }

        table.facts td {
            padding: 2px 0;
        }

        table.facts td.label {
            color: #444;
        }

        table.facts td.value {
            font-weight: bold;
            text-align: right;
        }

        table.facts td.text {
            font-weight: bold;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
        }

        table.report th,
        table.report td {
            border: 1px solid #999;
            text-align: center;
            padding: 3px 4px;
        }

        table.report th {
            background: #f2f2f2;
            font-weight: bold;
        }

        table.report tr.off td {
            background: #f7f7f7;
            color: #777;
        }

        table.report td.present {
            color: #15803d;
            font-weight: bold;
        }

        table.report td.late {
            color: #b45309;
            font-weight: bold;
        }

        table.report td.absent {
            color: #b91c1c;
            font-weight: bold;
        }

        table.report td.leave {
            color: #1d4ed8;
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
    @foreach ($reports as $report)
        @if (! $loop->first)
            <pagebreak />
        @endif

        <div class="header">
            <div class="school-name">{{ $schoolName }}</div>
            @if ($schoolAddressLine)
                <div class="school-address">{{ $schoolAddressLine }}</div>
            @endif
            <div class="title">{{ $report['type'] }} Attendance Report - {{ $monthLabel }}</div>
        </div>

        <table class="overview">
            <tr>
                <td class="box">
                    <div class="box-title">Profile</div>
                    <table class="facts">
                        <tr>
                            <td class="label" style="width: 32%;">Name</td>
                            <td class="text">{{ $report['name'] }}</td>
                        </tr>
                        <tr>
                            <td class="label">Designation</td>
                            <td class="text">{{ $report['designation'] ?: '-' }}</td>
                        </tr>
                    </table>
                </td>
                <td class="box">
                    <div class="box-title">Summary</div>
                    <table class="facts">
                        <tr>
                            <td class="label">Total Working Days</td>
                            <td class="value">{{ $report['summary']['working_days'] }}</td>
                        </tr>
                        <tr>
                            <td class="label">Total Present</td>
                            <td class="value">{{ $report['summary']['present'] }}</td>
                        </tr>
                        <tr>
                            <td class="label">Late Arrival</td>
                            <td class="value">{{ $report['summary']['late'] }}</td>
                        </tr>
                        <tr>
                            <td class="label">Total Absent</td>
                            <td class="value">{{ $report['summary']['absent'] }}</td>
                        </tr>
                        @if ($report['summary']['leave'] > 0)
                            <tr>
                                <td class="label">Leave</td>
                                <td class="value">{{ $report['summary']['leave'] }}</td>
                            </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>

        <table class="report">
            <thead>
                <tr>
                    <th style="width: 20%;">Date</th>
                    <th style="width: 18%;">Day</th>
                    <th style="width: 12%;">Status</th>
                    <th style="width: 16%;">Entry</th>
                    <th style="width: 16%;">Exit</th>
                    <th style="width: 18%;">Late Present</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['days'] as $day)
                    @php
                        [$mark, $markClass] = $marks[$day['status']?->value] ?? [$day['is_working_day'] ? '' : 'Off', ''];
                    @endphp
                    <tr class="{{ $day['is_working_day'] ? '' : 'off' }}">
                        <td>{{ $day['date']->format('d M Y') }}</td>
                        <td>{{ $day['date']->format('l') }}</td>
                        <td class="{{ $markClass }}">{{ $mark }}</td>
                        <td>{{ $day['entry'] ?? '' }}</td>
                        <td>{{ $day['exit'] ?? '' }}</td>
                        <td class="{{ $day['late_minutes'] !== null ? 'late' : '' }}">
                            {{ $day['late_minutes'] !== null ? $day['late_minutes'].' min' : '' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="legend">P = Present, LP = Late Present, A = Absent, L = Leave, Off = Weekend / Holiday</div>
    @endforeach
</body>

</html>
