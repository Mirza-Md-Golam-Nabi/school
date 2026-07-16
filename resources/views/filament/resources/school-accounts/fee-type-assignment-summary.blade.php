@if($feeType)
    <div class="w-full max-w-full overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-3 text-xs sm:text-sm">
        @if($feeType->schoolAccount)
            <p class="break-words">
                <span class="font-medium">{{ $feeType->name }}</span>
                is currently linked to <span class="font-semibold">{{ $feeType->schoolAccount->name }}</span> (বর্তমানে যুক্ত আছে)।
            </p>
            <p class="mt-1 break-words text-gray-600 dark:text-gray-400">
                Total posted all-time (সর্বমোট জমা হয়েছে):
                <span class="font-semibold text-green-600">৳{{ number_format($feeType->postedAmount(), 2) }}</span>
            </p>

            @if(! is_null($moveAmount))
                <p class="mt-1 break-words font-semibold {{ $moveAmount > 0 ? 'text-amber-600' : 'text-gray-500 dark:text-gray-400' }}">
                    This will move (এই পরিমাণ সরানো হবে): ৳{{ number_format($moveAmount, 2) }}
                </p>
            @elseif($resyncMode === 'none')
                <p class="mt-1 break-words font-semibold text-gray-500 dark:text-gray-400">
                    No amount will move — future payments only (কোনো টাকা সরবে না, শুধু ভবিষ্যৎ পেমেন্ট থেকে প্রযোজ্য হবে)।
                </p>
            @endif
        @else
            <p class="break-words text-gray-600 dark:text-gray-400">
                {{ $feeType->name }} is not linked to any fund yet (এখনো কোনো ফান্ডে যুক্ত নয়)।
            </p>
        @endif
    </div>
@endif
