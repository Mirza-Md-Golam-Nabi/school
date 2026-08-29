@php
    use App\Models\SchoolSetting;

    $schoolName = SchoolSetting::get('school_name', '');
    $schoolAddress = SchoolSetting::get('school_address', '');
    $schoolEstablishedYear = SchoolSetting::get('school_established_year', '');
    $schoolAddressLine = collect([
        $schoolAddress,
        $schoolEstablishedYear ? "Established: {$schoolEstablishedYear}" : null,
    ])->filter()->implode(' | ');
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 12mm 10mm;
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
            margin-bottom: 8px;
        }

        .list-info {
            text-align: center;
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

        table.list {
            width: 100%;
            border-collapse: collapse;
        }

        table.list th,
        table.list td {
            border: 1px solid #999;
            padding: 4px 6px;
        }

        table.list th {
            background: #f2f2f2;
            font-weight: bold;
            text-align: left;
        }

        table.list th.roll,
        table.list td.roll {
            width: 50px;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="school-name">{{ $schoolName }}</div>
        @if ($schoolAddressLine)
            <div class="school-address">{{ $schoolAddressLine }}</div>
        @endif
    </div>
    <div class="list-info">
        <div class="title">Student List</div>
        <div class="subtitle">{{ $class->name }} - Session: {{ $sessionYear }}</div>
    </div>

    <table class="list">
        <thead>
            <tr>
                <th class="roll">Roll</th>
                <th>Name</th>
                @if ($hasSection)
                    <th>Section</th>
                @endif
                @if ($hasGroup)
                    <th>Group</th>
                @endif
                <th>Email</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($students as $student)
                <tr>
                    <td class="roll">{{ sprintf('%02d', $student->roll_no) }}</td>
                    <td>{{ $student->user?->name }}</td>
                    @if ($hasSection)
                        <td>{{ $student->section?->name ?? '-' }}</td>
                    @endif
                    @if ($hasGroup)
                        <td>{{ $student->group?->name ?? '-' }}</td>
                    @endif
                    <td>{{ $student->user?->email }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>
