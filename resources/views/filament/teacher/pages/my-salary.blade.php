<x-filament-panels::page>
    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Due</p>
            <span class="text-xl font-bold text-danger-600 dark:text-danger-400 sm:text-2xl">
                ৳ {{ number_format($totalDue, 2) }}
            </span>
        </div>
    </div>

    <div>
        <h2 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Due</h2>

        @if ($dueInvoices->isEmpty())
            <x-filament::empty-state icon="heroicon-o-check-circle">
                <x-slot name="heading">কোনো বকেয়া নেই।</x-slot>
            </x-filament::empty-state>
        @else
            <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-700 dark:text-gray-300">Period</th>
                            <th class="px-3 py-2.5 text-center font-semibold text-gray-700 dark:text-gray-300">Status</th>
                            <th class="px-3 py-2.5 text-right font-semibold text-gray-700 dark:text-gray-300">Net</th>
                            <th class="px-3 py-2.5 text-right font-semibold text-gray-700 dark:text-gray-300">Due</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($dueInvoices as $invoice)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/30">
                                <td class="px-3 py-2.5 text-left">
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        {{ \Carbon\Carbon::create()->month($invoice->month)->format('F') }} {{ $invoice->year }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $invoice->invoice_no }}</p>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <x-filament::badge :color="$invoice->status->getColor()" size="sm">
                                        {{ $invoice->status->getLabel() }}
                                    </x-filament::badge>
                                </td>
                                <td class="px-3 py-2.5 text-right text-gray-700 dark:text-gray-300">৳ {{ number_format($invoice->net_amount, 2) }}</td>
                                <td class="px-3 py-2.5 text-right font-semibold text-danger-600 dark:text-danger-400">৳ {{ number_format($invoice->due_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div>
        <h2 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Paid</h2>

        @if ($paidInvoices->isEmpty())
            <x-filament::empty-state icon="heroicon-o-banknotes">
                <x-slot name="heading">এখনো কোনো পেমেন্ট রেকর্ড নেই।</x-slot>
            </x-filament::empty-state>
        @else
            <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-700 dark:text-gray-300">Period</th>
                            <th class="px-3 py-2.5 text-center font-semibold text-gray-700 dark:text-gray-300">Status</th>
                            <th class="px-3 py-2.5 text-right font-semibold text-gray-700 dark:text-gray-300">Net</th>
                            <th class="px-3 py-2.5 text-right font-semibold text-gray-700 dark:text-gray-300">Paid</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($paidInvoices as $invoice)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/30">
                                <td class="px-3 py-2.5 text-left">
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        {{ \Carbon\Carbon::create()->month($invoice->month)->format('F') }} {{ $invoice->year }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $invoice->invoice_no }}</p>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <x-filament::badge :color="$invoice->status->getColor()" size="sm">
                                        {{ $invoice->status->getLabel() }}
                                    </x-filament::badge>
                                </td>
                                <td class="px-3 py-2.5 text-right text-gray-700 dark:text-gray-300">৳ {{ number_format($invoice->net_amount, 2) }}</td>
                                <td class="px-3 py-2.5 text-right font-semibold text-success-600 dark:text-success-400">৳ {{ number_format($invoice->total_paid, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
