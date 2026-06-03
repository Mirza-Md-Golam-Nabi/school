@if($invoices->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">No outstanding invoices.</p>
@else
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-600 dark:text-gray-400">
                <tr>
                    <th class="px-2 py-2">Fee Type</th>
                    <th class="px-2 py-2">Period</th>
                    <th class="px-2 py-2 text-center">Net Amount</th>
                    <th class="px-2 py-2 text-center">Paid</th>
                    <th class="px-2 py-2 text-center">Due</th>
                    <th class="px-2 py-2 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($invoices as $invoice)
                    @php
                        $paid = $invoice->getTotalPaidAttribute();
                        $due  = max(0, (float) $invoice->net_amount - $paid);
                        $period = $invoice->month
                            ? \Carbon\Carbon::createFromFormat('!m', $invoice->month)->format('M') . ' ' . $invoice->year
                            : $invoice->year;
                    @endphp
                    <tr class="bg-white dark:bg-gray-900">
                        <td class="px-4 py-2 font-medium">{{ $invoice->feeType->name }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $period }}</td>
                        <td class="px-4 py-2 text-right">৳{{ number_format((float) $invoice->net_amount, 2) }}</td>
                        <td class="px-4 py-2 text-right text-green-600">৳{{ number_format($paid, 2) }}</td>
                        <td class="px-4 py-2 text-right font-semibold text-red-600">৳{{ number_format($due, 2) }}</td>
                        <td class="px-4 py-2 text-center">
                            <span @class([
                                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-red-100 text-red-700'    => $invoice->status === \App\Enums\InvoiceStatus::Unpaid,
                                'bg-yellow-100 text-yellow-700' => $invoice->status === \App\Enums\InvoiceStatus::Partial,
                            ])>
                                {{ $invoice->status->getLabel() }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 dark:bg-gray-800 font-semibold">
                <tr>
                    <td class="px-4 py-2" colspan="4">Total Outstanding</td>
                    <td class="px-4 py-2 text-right text-red-600">
                        ৳{{ number_format($invoices->sum(fn($inv) => max(0, (float) $inv->net_amount - $inv->getTotalPaidAttribute())), 2) }}
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif
