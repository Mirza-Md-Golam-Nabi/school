@php $discounts = collect($getState()); @endphp

<div class="overflow-x-auto">
    @if ($discounts->isEmpty())
        <p class="text-xs text-gray-400 italic dark:text-gray-500 sm:text-sm">No discounts have been applied.</p>
    @else
        <table class="w-full text-xs sm:text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-white/10 dark:text-gray-400">
                    <th class="py-2 pr-2 font-medium sm:pr-4">Fee Type</th>
                    <th class="py-2 pr-2 font-medium sm:pr-4">Discount</th>
                    <th class="py-2 pr-2 font-medium sm:pr-4">Value</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($discounts as $studentDiscount)
                    <tr class="border-b border-gray-100 last:border-0 dark:border-white/10">
                        <td class="py-2 pr-2 text-gray-800 dark:text-gray-200 sm:pr-4">{{ $studentDiscount['fee_type_name'] ?? '—' }}</td>
                        <td class="py-2 pr-2 text-gray-800 dark:text-gray-200 sm:pr-4">{{ $studentDiscount['discount_name'] ?? '—' }}</td>
                        <td class="py-2 sm:pr-4">
                            <x-filament::badge color="success" size="xs" class="!px-1 !py-1">
                                ৳{{ number_format((float) $studentDiscount['amount_in_taka'], 1) }}
                            </x-filament::badge>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
