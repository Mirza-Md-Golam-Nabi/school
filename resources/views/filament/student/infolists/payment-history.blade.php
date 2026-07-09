<div>
    @if ($getRecord()->payments->isEmpty())
        <p class="text-sm text-gray-400 italic dark:text-gray-500">No payments found.</p>
    @else
        @foreach ($getRecord()->payments as $payment)
            <div class="border-b border-gray-100 last:border-0 py-2 grid grid-cols-2 gap-x-4 gap-y-3 text-sm dark:border-white/10">
                <div>
                    <div class="text-gray-500 font-medium mb-1 dark:text-gray-400">Receipt</div>
                    <div class="text-gray-800 dark:text-gray-200">{{ $payment->receipt_no }}</div>
                </div>

                <div>
                    <div class="text-gray-500 font-medium mb-1 dark:text-gray-400">Amount</div>
                    <div class="text-gray-800 dark:text-gray-200">৳{{ number_format($payment->amount_paid, 2) }}</div>
                </div>

                <div>
                    <div class="text-gray-500 font-medium mb-1 dark:text-gray-400">Date</div>
                    <div class="text-gray-800 dark:text-gray-200">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') }}</div>
                </div>

                <div>
                    <div class="text-gray-500 font-medium mb-1 dark:text-gray-400">Method</div>
                    <div>
                        <x-filament::badge :color="$payment->payment_method->getColor()">
                            {{ $payment->payment_method->getLabel() }}
                        </x-filament::badge>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>