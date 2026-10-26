@php
    /**
     * A sample of each admit card design, side by side — the real PDF with a
     * made-up student and routine (AdmitCardSample), so it always matches
     * what will be generated.
     *
     * @var list<\App\Enums\AdmitCardDesign> $designs
     */
@endphp

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
    @foreach ($designs as $design)
        @php $sampleUrl = route('admit-cards.sample', $design); @endphp

        <div style="border: 1px solid rgba(148, 163, 184, 0.35); border-radius: 12px; overflow: hidden;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 12px; font-size: 13px; font-weight: 600; background: rgba(148, 163, 184, 0.08);">
                <span>নমুনা: {{ $design->getLabel() }}</span>
                <a href="{{ $sampleUrl }}" target="_blank" rel="noopener" style="font-size: 12px; font-weight: 500; color: rgb(var(--primary-600, 79 70 229)); white-space: nowrap;">বড় করে দেখুন ↗</a>
            </div>
            <iframe
                src="{{ $sampleUrl }}#toolbar=0&navpanes=0&view=FitH"
                title="Admit card sample: {{ $design->getLabel() }}"
                loading="lazy"
                style="display: block; width: 100%; height: 320px; border: 0; background: #ffffff;"
            ></iframe>
        </div>
    @endforeach
</div>
