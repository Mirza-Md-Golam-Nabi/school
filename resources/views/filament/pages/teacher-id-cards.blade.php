<x-filament-panels::page>
    @include('filament.pages.partials.student-id-card-design-picker', [
        'cardRole' => 'TEACHER',
        'sampleName' => 'Teacher Name',
        'sampleFrontRows' => ['ID NO' => 'T0010001', 'POST' => 'Asst. Teacher', 'BLOOD' => 'O+'],
        'sampleBackRows' => ['PHONE' => '01700-000000', 'EMAIL' => 'teacher@school.edu', 'BLOOD GROUP' => 'O+'],
        'sampleFooter' => 'SINCE',
        'sampleIdNumber' => 'T0010001',
        'pickerDescription' => 'Teacher-দের ID card-এর ডিজাইন বেছে নিন (student card থেকে আলাদা রাখলে সহজে চেনা যায়), লোগোর রং ঠিক করুন, প্রিভিউ দেখে Submit চাপুন।',
    ])

    @if (! $hasSubmittedDesign)
        <x-filament::empty-state
            icon="heroicon-o-swatch"
            heading="আগে কার্ডের ডিজাইন সাবমিট করুন"
            description="ওপর থেকে ডিজাইন বেছে প্রিভিউ দেখে Submit Design চাপুন — তারপর teacher-দের ID card তৈরি করা যাবে।"
        />
    @elseif ($teachers->isEmpty())
        <x-filament::empty-state
            icon="heroicon-o-identification"
            heading="No active teachers found"
            description="Add active teachers first to generate teacher ID cards."
        />
    @else
        <x-filament::section
            icon="heroicon-o-users"
            heading="Teachers"
            description="কার্ডের ছবি হলো teacher-এর নিজের profile photo — teacher নিজে 'My ID Card' পেজ থেকে ছবির পজিশন ঠিক করতে পারে।"
        >
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                @foreach ($teachers as $teacher)
                    @php
                        $avatarUrl = $teacher->user?->getFilamentAvatarUrl();
                    @endphp

                    <div
                        wire:key="teacher-id-card-{{ $teacher->id }}"
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
                            <p class="truncate text-xs font-bold text-gray-900 sm:text-sm dark:text-white">{{ $teacher->user?->name }}</p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                {{ $teacher->designation ?: '—' }}
                                @unless ($avatarUrl)
                                    · <span class="text-warning-600 dark:text-warning-400">No photo</span>
                                @endunless
                            </p>
                        </div>

                        <x-filament::button
                            tag="a"
                            :href="route('teacher-id-cards.teacher.view', ['teacher' => $teacher->id])"
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
