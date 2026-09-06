@php
    use App\Models\SchoolSetting;
    use Carbon\Carbon;

    $firstPayment = $payments->first();
    $student = $firstPayment->student;
    $baseReceiptNo = preg_replace('#/\d+$#', '', $firstPayment->receipt_no);
    $totalPaid = (float) $payments->sum('amount_paid');

    $amountToWords = function (float $amount): string {
        $taka = (int) floor($amount);
        $poisha = (int) round(($amount - $taka) * 100);

        $spellout = new NumberFormatter('en', NumberFormatter::SPELLOUT);

        $words = ucwords($spellout->format($taka)).' Taka';

        if ($poisha > 0) {
            $words .= ' And '.ucwords($spellout->format($poisha)).' Poisha';
        }

        return $words.' Only';
    };

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
            margin: 16px;
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

        .school-name {
            font-size: 18px;
            font-weight: bold;
        }

        .school-address {
            font-size: 11px;
            color: #444;
        }

        .title {
            width: 150px;
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
            width: 100px;
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

        table.subjects td.amount,
        table.subjects th.amount {
            text-align: right;
        }

        table.subjects tr.total td {
            font-weight: bold;
            background: #f2f2f2;
        }

        .words {
            margin-top: 8px;
            font-size: 11px;
            font-style: italic;
        }

        .signatures {
            margin-top: 24px;
            width: 100%;
        }

        .signatures td {
            text-align: center;
            font-size: 11px;
            padding-top: 48px;
            border-top: 1px solid #1a1a1a;
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
            @if ($schoolAddressLine)
                <div class="school-address">{{ $schoolAddressLine }}</div>
            @endif
        </div>

        <div class="title">Payment Slip</div>

        <table class="info">
            <tr>
                <td class="label">Student Name</td>
                <td colspan="3">{{ $student->user?->name }}</td>
            </tr>
            <tr>
                <td class="label">Class</td>
                <td>{{ $student->class?->name }}</td>
                <td class="label">Roll No</td>
                <td>{{ sprintf('%02d', $student->roll_no) }}</td>
            </tr>
            @if ($student->group && $student->section)
                <tr>
                    <td class="label">Group</td>
                    <td>{{ $student->group?->name }}</td>
                    <td class="label">Section</td>
                    <td>{{ $student->section?->name }}</td>
                </tr>
            @elseif($student->group)
                <tr>
                    <td class="label">Group</td>
                    <td colspan="3">{{ $student->group?->name }}</td>
                </tr>
            @elseif($student->section)
                <tr>
                    <td class="label">Section</td>
                    <td colspan="3">{{ $student->section?->name }}</td>
                </tr>
            @endif
            <tr>
                <td class="label">Receipt No.</td>
                <td>{{ $baseReceiptNo }}</td>
                <td class="label">Date</td>
                <td>{{ $firstPayment->payment_date?->format('d M Y') }}</td>
            </tr>
            <tr>
                <td class="label">Payment By</td>
                <td>{{ $firstPayment->payment_method?->getLabel() }}</td>
                <td class="label">Received By</td>
                <td>{{ $firstPayment->receivedBy?->name }}</td>
            </tr>
        </table>

        <table class="subjects">
            <thead>
                <tr>
                    <th style="width: 30px;">SL</th>
                    <th>Fee Type</th>
                    <th>Period</th>
                    <th class="amount">Paid Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payments as $index => $payment)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $payment->invoice?->feeType?->name }}</td>
                        <td style="text-align: center;">
                            {{ $payment->invoice?->month
                                ? Carbon::create()->month($payment->invoice->month)->format('F').' '.$payment->invoice->year
                                : (string) $payment->invoice?->year }}
                        </td>
                        <td class="amount">{{ number_format((float) $payment->amount_paid, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="3" class="amount">Total Paid</td>
                    <td class="amount">৳ {{ number_format((float) $totalPaid, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="words">In Words: {{ $amountToWords($totalPaid) }}</div>

        <table class="signatures">
            <tr>
                <td>Received By</td>
                <td>Authorized Signature</td>
            </tr>
        </table>
    </div>
</body>

</html>
