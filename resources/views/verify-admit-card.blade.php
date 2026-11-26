@php
    use App\Enums\StudentStatus;
    use App\Support\BengaliNumber;

    /** @var \App\Models\AdmitCard|null $admitCard */
    $student = $admitCard?->student;
    $exam = $admitCard?->exam;

    $examEnded = $exam?->end_date && $exam->end_date->endOfDay()->isPast();
    $studentInactive = $student && $student->status !== StudentStatus::Active;

    $details = $admitCard ? collect([
        ['শিক্ষার্থীর নাম', $student?->user?->name],
        ['স্টুডেন্ট আইডি', BengaliNumber::convert((string) $student?->idCardNumber())],
        ['শ্রেণি', $student?->class?->display_name],
        ['রোল নং', BengaliNumber::convert(sprintf('%02d', $student?->roll_no))],
        ['শাখা', $student?->section?->name],
        ['গ্রুপ', $student?->group?->display_name],
        ['পরীক্ষা', $exam?->examType?->name.' ('.BengaliNumber::convert((string) $exam?->session_label).')'],
        ['পরীক্ষার সময়', $exam?->start_date && $exam?->end_date
            ? BengaliNumber::convert($exam->start_date->format('d/m/Y')).' – '.BengaliNumber::convert($exam->end_date->format('d/m/Y'))
            : null],
    ])->filter(fn (array $detail): bool => filled($detail[1])) : collect();
@endphp
@extends('verify-layout')

@section('title', 'প্রবেশপত্র যাচাই')

@section('content')
    @if ($admitCard)
        <div class="status status--ok">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            <span>বৈধ প্রবেশপত্র — স্কুল থেকে ইস্যু করা</span>
        </div>

        @if ($studentInactive)
            <div class="note note--bad">এই শিক্ষার্থী বর্তমানে স্কুলে সক্রিয় নন।</div>
        @endif

        @if ($examEnded)
            <div class="note note--warn">এই পরীক্ষাটি শেষ হয়ে গেছে।</div>
        @endif

        <div class="body">
            @if ($photoUrl = $student?->photoUrl())
                <img class="photo" src="{{ $photoUrl }}" alt="শিক্ষার্থীর ছবি">
            @else
                <div class="photo photo--empty">ছবি নেই</div>
            @endif

            <table class="details">
                @foreach ($details as [$label, $value])
                    <tr>
                        <th>{{ $label }}</th>
                        <td>{{ $value }}</td>
                    </tr>
                @endforeach
            </table>

            <p class="hint">কার্ডের ছবি ও তথ্য এই পেজের সঙ্গে মিলিয়ে দেখুন।</p>
        </div>
    @else
        <div class="status status--bad">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
            <span>প্রবেশপত্র পাওয়া যায়নি</span>
        </div>
        <div class="body">
            <p style="margin: 0;">এই QR কোডের কোনো প্রবেশপত্র স্কুলের রেকর্ডে নেই। কার্ডটি জাল, পুরোনো বা বাতিল হতে পারে — স্কুল অফিসে যোগাযোগ করুন।</p>
        </div>
    @endif
@endsection
