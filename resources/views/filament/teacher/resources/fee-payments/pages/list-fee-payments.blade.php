@php
    use App\Enums\PaymentMethod;
    use App\Filament\Teacher\Resources\FeePayments\FeePaymentResource;
    use App\Support\ClassAccentColor;
@endphp

<x-filament-panels::page>
    @if ($classes->isEmpty())
        <x-filament::empty-state
            icon="heroicon-o-credit-card"
            heading="No assigned classes"
            description="You are not the class teacher for any class yet."
        />
    @else
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @foreach ($classes as $class)
                @php
                    $accent  = ClassAccentColor::for($class->id);
                    $url     = FeePaymentResource::getUrl('class-payments', ['class' => $class->id]);
                    $visible = $class->paymentStats->take(3);
                    $hidden  = max(0, $class->paymentStats->count() - 3);
                @endphp

                <a
                    href="{{ $url }}"
                    class="group flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 transition-all duration-200 hover:-translate-y-1 hover:shadow-lg dark:bg-gray-900 dark:ring-white/10"
                >
                    {{-- Accent bar --}}
                    <div class="h-1.5" style="background-color:{{ $accent }}"></div>

                    {{-- Header --}}
                    <div class="flex items-center gap-2 px-3 pt-3 pb-2">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                             style="background-color:{{ $accent }}22">
                            <x-heroicon-o-credit-card class="h-4 w-4" style="color:{{ $accent }}" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-gray-900 dark:text-white">
                                {{ $class->name }}
                            </p>
                            <x-filament::badge
                                size="sm"
                                :color="$class->total_payments > 0 ? 'success' : 'gray'"
                            >
                                {{ $class->total_payments }} {{ Str::plural('payment', $class->total_payments) }}
                            </x-filament::badge>
                        </div>
                    </div>

                    {{-- Payment method breakdown --}}
                    <div class="flex-1 space-y-1 px-3 pb-2">
                        @forelse ($visible as $stat)
                            @php $method = PaymentMethod::from($stat->getRawOriginal('payment_method')); @endphp
                            <div class="flex items-center justify-between rounded bg-gray-50 px-2 py-1 dark:bg-gray-800">
                                <span class="flex items-center gap-1.5 text-[11px] text-gray-600 dark:text-gray-300">
                                    <span class="inline-block h-1 w-1 shrink-0 rounded-full"
                                          style="background-color:{{ $accent }}"></span>
                                    {{ $method->getLabel() }}
                                </span>
                                <div class="flex items-center gap-1">
                                    <x-filament::badge size="sm" :color="$method->getColor()">
                                        {{ $stat->count }}
                                    </x-filament::badge>
                                    <span class="text-[10px] font-semibold text-gray-500 dark:text-gray-400">
                                        ৳{{ number_format($stat->total_amount, 0) }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="rounded border border-dashed border-gray-200 px-2 py-3 text-center dark:border-gray-700">
                                <p class="text-[11px] text-gray-400 dark:text-gray-500">No payments yet</p>
                            </div>
                        @endforelse

                        @if ($hidden > 0)
                            <p class="pt-0.5 text-center text-[10px] text-gray-400 dark:text-gray-500">
                                +{{ $hidden }} more — click to view all
                            </p>
                        @endif
                    </div>

                    {{-- Footer: total collected --}}
                    <div class="flex items-center justify-between border-t border-gray-100 px-3 py-2 dark:border-gray-800">
                        @if ($class->total_collected > 0)
                            <div>
                                <p class="text-xs font-bold text-success-600 dark:text-success-400">
                                    ৳{{ number_format($class->total_collected, 0) }}
                                </p>
                                <p class="text-[10px] leading-tight text-gray-500 dark:text-gray-400">
                                    Total collected
                                </p>
                            </div>
                        @else
                            <p class="text-[10px] text-gray-400 dark:text-gray-500">No payments yet</p>
                        @endif

                        <x-filament::icon-button
                            icon="heroicon-o-arrow-right"
                            size="sm"
                            color="gray"
                            tag="span"
                            class="transition-transform duration-150 group-hover:translate-x-0.5"
                        />
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
