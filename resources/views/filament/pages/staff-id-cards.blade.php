<x-filament-panels::page>
    @include('filament.pages.partials.student-id-card-design-picker', [
        'cardRole' => 'STAFF',
        'sampleName' => 'Staff Name',
        'sampleFrontRows' => ['ID NO' => 'S0010001', 'POST' => 'Office Assistant', 'BLOOD' => 'O+'],
        'sampleBackRows' => ['PHONE' => '01700-000000', 'JOINING DATE' => '01 Jan 2020', 'BLOOD GROUP' => 'O+'],
        'sampleFooter' => 'SINCE',
        'sampleIdNumber' => 'S0010001',
        'pickerDescription' => 'Staff-দের ID card-এর ডিজাইন বেছে নিন (student ও teacher card থেকে আলাদা রাখলে সহজে চেনা যায়), লোগোর রং ঠিক করুন, প্রিভিউ দেখে Submit চাপুন।',
    ])

    @if (! $hasSubmittedDesign)
        <x-filament::empty-state
            icon="heroicon-o-swatch"
            heading="আগে কার্ডের ডিজাইন সাবমিট করুন"
            description="ওপর থেকে ডিজাইন বেছে প্রিভিউ দেখে Submit Design চাপুন — তারপর staff-দের ID card তৈরি করা যাবে।"
        />
    @elseif ($staffMembers->isEmpty())
        <x-filament::empty-state
            icon="heroicon-o-identification"
            heading="No active staff found"
            description="Add active staff first to generate staff ID cards."
        />
    @else
        <x-filament::section
            icon="heroicon-o-users"
            heading="Staff"
            description="কার্ডের ছবি হলো staff-এর profile photo — staff নিজে প্যানেলের Edit Profile থেকে ছবি দিতে পারে।"
        >
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                @foreach ($staffMembers as $staff)
                    @php
                        $avatarUrl = $staff->user?->getFilamentAvatarUrl();
                    @endphp

                    <div
                        wire:key="staff-id-card-{{ $staff->id }}"
                        class="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                    >
                        @if ($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="" class="h-10 w-10 shrink-0 rounded-full object-cover">
                        @else
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                <x-heroicon-o-user class="h-5 w-5 text-gray-400" />
                            </div>
                        @endif

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-bold text-gray-900 sm:text-sm dark:text-white">{{ $staff->user?->name }}</p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                {{ $staff->designation ?: '—' }}
                                @unless ($avatarUrl)
                                    · <span class="text-warning-600 dark:text-warning-400">No photo</span>
                                @endunless
                            </p>
                        </div>

                        <x-filament::button
                            tag="a"
                            :href="route('staff-id-cards.staff.view', ['staff' => $staff->id])"
                            target="_blank"
                            icon="heroicon-o-eye"
                            size="xs"
                            color="gray"
                        >
                            View
                        </x-filament::button>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
