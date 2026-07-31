<x-filament-widgets::widget>
    @if ($isVisible)
        <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-4">
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs font-bold tracking-tight text-gray-900 dark:text-white sm:text-lg">
                    {{ $transactionType->getLabel() }} Breakdown
                </p>

                <a
                    href="{{ $backUrl }}"
                    wire:navigate
                    class="shrink-0 text-xs font-medium text-primary-600 hover:underline dark:text-primary-400 sm:text-sm"
                >
                    &larr; All Transactions
                </a>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-3 sm:mt-4">
                @foreach ($breakdown as $label => $amount)
                    <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-4">
                        <p class="truncate text-xs font-medium text-gray-500 dark:text-gray-400 sm:text-sm">
                            {{ $label }}
                        </p>

                        <p
                            @class([
                                'mt-2 text-xs font-bold sm:mt-3 sm:text-2xl',
                                'text-success-600 dark:text-success-400' => $transactionType->value === 'income',
                                'text-danger-600 dark:text-danger-400' => $transactionType->value === 'expense',
                            ])
                        >
                            ৳ {{ number_format($amount, 2) }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
