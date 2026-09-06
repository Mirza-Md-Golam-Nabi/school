@php
    use App\Enums\InvoiceStatus;

    $student = $getRecord();

    $pendingInvoices = $student->feeInvoices()
        ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
        ->with('payments')
        ->get();

    $totalDue = $pendingInvoices->sum(fn ($invoice) => max(0, (float) $invoice->net_amount - $invoice->payments->sum('amount_paid')));

    $totalPaid = (float) $student->feePayments()->sum('amount_paid');

    $recentPayments = $student->feePayments()
        ->with('invoice.feeType')
        ->latest('payment_date')
        ->take(5)
        ->get();
@endphp

<div class="space-y-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-lg border border-gray-100 p-3 dark:border-white/10 sm:p-4">
            <div class="text-[11px] font-medium text-gray-500 dark:text-gray-400 sm:text-xs">Total Paid</div>
            <div class="mt-1 text-base font-bold text-success-600 dark:text-success-400 sm:text-lg">
                ৳{{ number_format($totalPaid, 2) }}
            </div>
        </div>

        <div class="rounded-lg border border-gray-100 p-3 dark:border-white/10 sm:p-4">
            <div class="text-[11px] font-medium text-gray-500 dark:text-gray-400 sm:text-xs">Total Due</div>
            <div @class([
                'mt-1 text-base font-bold sm:text-lg',
                'text-danger-600 dark:text-danger-400' => $totalDue > 0,
                'text-success-600 dark:text-success-400' => $totalDue <= 0,
            ])>
                ৳{{ number_format($totalDue, 2) }}
            </div>
        </div>

        <div class="rounded-lg border border-gray-100 p-3 dark:border-white/10 sm:p-4">
            <div class="text-[11px] font-medium text-gray-500 dark:text-gray-400 sm:text-xs">Pending Invoices</div>
            <div class="mt-1 text-base font-bold text-gray-800 dark:text-gray-200 sm:text-lg">
                {{ $pendingInvoices->count() }}
            </div>
        </div>
    </div>

    <div>
        <div class="mb-2 text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400 sm:text-xs">Recent Payments</div>

        @if ($recentPayments->isEmpty())
            <p class="text-xs text-gray-400 italic dark:text-gray-500 sm:text-sm">No payments found.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-xs sm:text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <th class="py-2 pr-2 font-medium sm:pr-4">Receipt</th>
                            <th class="py-2 pr-2 font-medium sm:pr-4">Fee Type</th>
                            <th class="py-2 pr-2 text-right font-medium sm:pr-4">Amount</th>
                            <th class="py-2 text-right font-medium">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentPayments as $payment)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-white/10">
                                <td class="py-2 pr-2 text-gray-800 dark:text-gray-200 sm:pr-4">{{ $payment->receipt_no }}</td>
                                <td class="py-2 pr-2 text-gray-600 dark:text-gray-400 sm:pr-4">{{ $payment->invoice?->feeType?->name ?? '—' }}</td>
                                <td class="py-2 pr-2 text-right font-semibold whitespace-nowrap text-gray-900 dark:text-white sm:pr-4">৳{{ number_format((float) $payment->amount_paid, 2) }}</td>
                                <td class="py-2 text-right whitespace-nowrap text-gray-600 dark:text-gray-400">{{ $payment->payment_date?->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
