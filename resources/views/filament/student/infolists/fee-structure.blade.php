@php $structures = collect($getState()); @endphp

<div class="overflow-x-auto">
    @if ($structures->isEmpty())
        <p class="text-xs text-gray-400 italic dark:text-gray-500 sm:text-sm">No fee structure found for the current class and session.</p>
    @else
        <table class="w-full text-xs sm:text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-white/10 dark:text-gray-400">
                    <th class="py-2 pr-2 font-medium sm:pr-4">Fee Type</th>
                    <th class="py-2 pr-2 font-medium sm:pr-4">Frequency</th>
                    <th class="py-2 text-right font-medium">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($structures as $structure)
                    <tr class="border-b border-gray-100 last:border-0 dark:border-white/10">
                        <td class="py-2 pr-2 text-gray-800 dark:text-gray-200 sm:pr-4">{{ $structure->feeType?->name ?? '—' }}</td>
                        <td class="py-2 pr-2 text-gray-600 dark:text-gray-400 sm:pr-4">{{ $structure->feeType?->is_monthly ? 'Monthly' : 'One-time' }}</td>
                        <td class="py-2 text-right font-semibold whitespace-nowrap text-gray-900 dark:text-white">৳{{ number_format((float) $structure->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
