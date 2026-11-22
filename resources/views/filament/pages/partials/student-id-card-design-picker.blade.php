@php
    // The student ID card page uses the defaults; the teacher page passes its own wording.
    $sample = [
        'cardRole' => $cardRole ?? 'STUDENT',
        'sampleName' => $sampleName ?? 'Student Name',
        'sampleFrontRows' => $sampleFrontRows ?? ['ID NO' => '20260001', 'CLASS' => 'Six', 'ROLL' => '12'],
        'sampleBackRows' => $sampleBackRows ?? ['GUARDIAN' => 'Guardian Name', 'PHONE' => '01700-000000', 'BLOOD GROUP' => 'B+'],
        'sampleFooter' => $sampleFooter ?? 'ADMISSION',
        'schoolName' => $schoolName,
        'schoolAddress' => $schoolAddress,
        'logo' => $previewLogo,
    ];
    $pickerDescription ??= 'ডিজাইন বেছে নিন, লোগোর রং ঠিক করুন, প্রিভিউ দেখে Submit চাপুন — সাবমিট করা ডিজাইনেই সব ক্লাসের ID card তৈরি হবে।';

    $draft = collect($templates)->firstWhere('value', $draftTemplate) ?? $templates[0];
    $logoSwatches = ['#ffffff', '#000000', $draft['theme']['schoolName'], $draft['theme']['roleBackground'], $draft['theme']['footerAccent']];
@endphp

<x-filament::section
    icon="heroicon-o-swatch"
    heading="Card Design"
    :description="$pickerDescription"
>
    <div class="flex flex-col gap-6">
        {{-- ================= Step 1: designs ================= --}}
        <div>
            <p class="mb-2 text-xs font-semibold text-gray-700 sm:text-sm dark:text-gray-200">১. ডিজাইন বেছে নিন</p>

            <div class="flex flex-wrap justify-center gap-3 sm:justify-start">
                @foreach ($templates as $template)
                    @php
                        $isDraft = $template['value'] === $draftTemplate;
                        $isSubmitted = $hasSubmittedDesign && $template['value'] === $selectedTemplate;
                    @endphp

                    <button
                        type="button"
                        wire:click="selectTemplate('{{ $template['value'] }}')"
                        wire:loading.attr="disabled"
                        wire:key="id-card-template-{{ $template['value'] }}"
                        aria-pressed="{{ $isDraft ? 'true' : 'false' }}"
                        class="rounded-xl bg-white p-1.5 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:bg-gray-900"
                        style="border: 2px solid {{ $isDraft ? '#2563eb' : 'rgba(148,163,184,0.35)' }}; {{ $isDraft ? 'box-shadow: 0 0 0 3px rgba(37,99,235,0.2);' : '' }}"
                    >
                        @include('filament.pages.partials.id-card-design-sample', [...$sample, 'template' => $template, 'u' => 1.6])

                        <div class="mt-1.5 flex items-center justify-between gap-1 px-0.5">
                            <p class="truncate text-xs font-bold text-gray-900 dark:text-white">{{ $template['label'] }}</p>

                            @if ($isSubmitted)
                                <x-filament::badge color="success" icon="heroicon-m-check-circle" size="sm">Active</x-filament::badge>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- ================= Step 2: preview, logo colour and submit ================= --}}
        <div class="flex flex-col gap-4 rounded-xl bg-gray-50 p-3 ring-1 ring-gray-950/5 sm:p-4 lg:flex-row lg:items-start dark:bg-white/5 dark:ring-white/10">
            <div class="min-w-0 lg:flex-1">
                <p class="mb-2 text-xs font-semibold text-gray-700 sm:text-sm dark:text-gray-200">
                    ২. প্রিভিউ — {{ $draft['label'] }}
                    <span class="font-normal text-gray-500 dark:text-gray-400">· {{ $draft['description'] }}</span>
                </p>

                <div class="overflow-x-auto" wire:loading.class="opacity-60">
                    @include('filament.pages.partials.id-card-design-sample', [...$sample, 'template' => $draft, 'u' => 3.8])
                </div>
            </div>

            <div class="flex flex-col gap-4 lg:w-72 lg:shrink-0">
                <div>
                    <p class="mb-2 text-xs font-semibold text-gray-700 sm:text-sm dark:text-gray-200">৩. লোগোর রং</p>

                    @if (! $hasSchoolLogo)
                        <p class="text-xs text-warning-600 dark:text-warning-400">
                            এখনো কোনো স্কুল লোগো নেই — School Settings থেকে লোগো আপলোড করুন।
                        </p>
                    @else
                        <div class="grid grid-cols-2 gap-1 rounded-lg bg-white p-1 ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10">
                            <button
                                type="button"
                                wire:click="$set('recolourLogo', false)"
                                @class([
                                    'rounded-md px-2 py-1.5 text-xs font-semibold transition',
                                    'bg-primary-600 text-white' => ! $recolourLogo,
                                    'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' => $recolourLogo,
                                ])
                            >
                                আসল রং
                            </button>
                            <button
                                type="button"
                                wire:click="$set('recolourLogo', true)"
                                @class([
                                    'rounded-md px-2 py-1.5 text-xs font-semibold transition',
                                    'bg-primary-600 text-white' => $recolourLogo,
                                    'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' => ! $recolourLogo,
                                ])
                            >
                                নিজের রং
                            </button>
                        </div>

                        @if ($recolourLogo)
                            <div class="mt-3 flex items-center gap-2">
                                <input
                                    type="color"
                                    wire:model.live.debounce.300ms="draftLogoColour"
                                    class="h-9 w-12 shrink-0 cursor-pointer rounded border border-gray-300 bg-white p-0.5 dark:border-white/10 dark:bg-gray-900"
                                    aria-label="Logo colour"
                                >
                                <x-filament::input.wrapper class="min-w-0 flex-1" :valid="! $errors->has('draftLogoColour')">
                                    <x-filament::input
                                        type="text"
                                        wire:model.live.debounce.500ms="draftLogoColour"
                                        maxlength="7"
                                        placeholder="#ffffff"
                                        class="text-xs uppercase sm:text-sm"
                                    />
                                </x-filament::input.wrapper>
                            </div>

                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach (array_unique(array_map('strtolower', $logoSwatches)) as $swatch)
                                    <button
                                        type="button"
                                        wire:click="$set('draftLogoColour', '{{ $swatch }}')"
                                        title="{{ $swatch }}"
                                        class="h-6 w-6 rounded-full ring-1 ring-gray-950/20 transition hover:scale-110 dark:ring-white/20"
                                        style="background: {{ $swatch }}; {{ strtolower($draftLogoColour) === $swatch ? 'outline: 2px solid #2563eb; outline-offset: 2px;' : '' }}"
                                    ></button>
                                @endforeach
                            </div>

                            @error('draftLogoColour')
                                <p class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        @endif
                    @endif
                </div>

                <div class="flex flex-col gap-2 border-t border-gray-200 pt-3 dark:border-white/10">
                    @if (! $hasSubmittedDesign)
                        <p class="text-xs text-warning-600 dark:text-warning-400">
                            ডিজাইন এখনো সাবমিট হয়নি — সাবমিট করার পরেই ID card তৈরি করা যাবে।
                        </p>
                    @elseif ($hasUnsubmittedChanges)
                        <p class="text-xs text-warning-600 dark:text-warning-400">
                            প্রিভিউতে পরিবর্তন সাবমিট হয়নি — এখন কার্ড আগের ডিজাইনেই তৈরি হবে।
                        </p>
                    @else
                        <p class="text-xs text-success-600 dark:text-success-400">
                            এই ডিজাইনটাই সাবমিট করা আছে — ID card এই ডিজাইনে তৈরি হচ্ছে।
                        </p>
                    @endif

                    <x-filament::button
                        wire:click="submitDesign"
                        icon="heroicon-o-check-badge"
                        :disabled="! $hasUnsubmittedChanges"
                        class="w-full"
                    >
                        Submit Design
                    </x-filament::button>
                </div>
            </div>
        </div>
    </div>
</x-filament::section>
