@php
    $student = $getRecord();
    $user = $student->user;
    $avatarUrl = $user?->getFilamentAvatarUrl();
    $initial = $user?->name ? mb_strtoupper(mb_substr($user->name, 0, 1)) : '?';

    // One of 3 gradients, picked per student so the color stays stable across
    // page loads instead of flipping every time the page is viewed.
    $gradients = [
        'from-primary-600 to-primary-400 dark:from-primary-700 dark:to-primary-500',
        'from-success-600 to-success-400 dark:from-success-700 dark:to-success-500',
        'from-danger-600 to-danger-400 dark:from-danger-700 dark:to-danger-500',
    ];
    $gradient = $gradients[$student->id % count($gradients)];
@endphp

<div class="relative overflow-hidden rounded-xl bg-gradient-to-r {{ $gradient }} p-4 text-white shadow-sm sm:p-6">
    <div class="flex flex-col items-center gap-4 sm:flex-row">
        <div class="shrink-0">
            @if ($avatarUrl)
                <img
                    src="{{ $avatarUrl }}"
                    alt="{{ $user?->name }}"
                    class="h-20 w-20 rounded-full object-cover ring-4 ring-white/50 sm:h-24 sm:w-24"
                >
            @else
                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-white/20 text-2xl font-bold ring-4 ring-white/50 sm:h-24 sm:w-24 sm:text-3xl">
                    {{ $initial }}
                </div>
            @endif
        </div>

        <div class="flex-1 text-center sm:text-left">
            <div class="text-lg font-bold sm:text-2xl">{{ $user?->name ?? 'Unknown Student' }}</div>

            @if ($student->registration_no)
                <div class="mt-0.5 text-xs text-white/80 sm:text-sm">Reg: {{ $student->registration_no }}</div>
            @endif

            <div class="mt-3 flex flex-wrap justify-center gap-1.5 sm:justify-start">
                <x-filament::badge class="!bg-white/20 !text-white">Roll: {{ sprintf('%02d', $student->roll_no) }}</x-filament::badge>

                @if ($student->class)
                    <x-filament::badge class="!bg-white/20 !text-white">{{ $student->class->name }}</x-filament::badge>
                @endif

                @if ($student->section)
                    <x-filament::badge class="!bg-white/20 !text-white">{{ $student->section->name }}</x-filament::badge>
                @endif

                @if ($student->group)
                    <x-filament::badge class="!bg-white/20 !text-white">{{ $student->group->name }}</x-filament::badge>
                @endif

                <x-filament::badge class="!bg-white/20 !text-white">Session: {{ $student->session_year }}</x-filament::badge>

                <x-filament::badge :color="$student->status->getColor()">{{ $student->status->getLabel() }}</x-filament::badge>
            </div>
        </div>
    </div>
</div>
