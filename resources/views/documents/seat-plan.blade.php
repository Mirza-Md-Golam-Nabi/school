<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 10mm;
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

        table.sheet tr {
            page-break-inside: avoid;
        }

        table.sheet td.cell {
            width: 33.33%;
            padding: 6mm 3mm;
            vertical-align: top;
        }

        table.card {
            width: 100%;
            border-collapse: collapse;
        }

        table.card td.card-inner {
            border: 1.2px solid #1a1a1a;
            padding: 5px;
        }

        table.info-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.info-table td {
            padding: 4px 8px;
            vertical-align: top;
            font-size: 12px;
        }

        table.info-table td.label {
            font-weight: bold;
            width: 45px;
        }

        table.info-table tr.roll td {
            font-size: 12px;
            font-weight: bold;
            padding-bottom: 6px;
        }
    </style>
</head>

<body>
    <table class="sheet">
        @foreach ($students->chunk(3) as $row)
            <tr>
                @foreach ($row as $student)
                    <td class="cell">
                        <table class="card">
                            <tr>
                                <td class="card-inner">
                                    <table class="info-table">
                                        <tr class="roll">
                                            <td class="label">Roll</td>
                                            <td>{{ sprintf('%02d', $student->roll_no) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="label">Name</td>
                                            <td>{{ $student->user?->name }}</td>
                                        </tr>
                                        <tr>
                                            <td class="label">Class</td>
                                            <td>{{ $student->class?->name }}</td>
                                        </tr>
                                        @if ($student->group)
                                            <tr>
                                                <td class="label">Group</td>
                                                <td>{{ $student->group?->name ?? "-" }}</td>
                                            </tr>
                                        @endif
                                        @if ($student->section)
                                            <tr>
                                                <td class="label">Section</td>
                                                <td>{{ $student->section?->name ?? "-" }}</td>
                                            </tr>
                                        @endif
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                @endforeach
                @for ($i = $row->count(); $i < 3; $i++)
                    <td class="cell"></td>
                @endfor
            </tr>
        @endforeach
    </table>
</body>

</html>
