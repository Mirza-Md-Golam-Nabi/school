@php $addresses = collect($getState()); @endphp

<div>
    @if ($addresses->isEmpty())
        <p class="text-xs text-gray-400 italic dark:text-gray-500 sm:text-sm">No address information found.</p>
    @else
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4">
            @foreach ($addresses as $address)
                <div class="rounded-lg border border-gray-100 p-2.5 dark:border-white/10 sm:p-3">
                    <div class="mb-1 text-[11px] font-medium text-gray-500 dark:text-gray-400 sm:text-xs">
                        {{ $address->type === \App\Enums\AddressType::Present ? 'Present Address' : 'Permanent Address' }}
                    </div>
                    <div class="text-xs text-gray-800 dark:text-gray-200 sm:text-sm">{{ $address->address ?: '—' }}</div>
                </div>
            @endforeach
        </div>
    @endif
</div>
