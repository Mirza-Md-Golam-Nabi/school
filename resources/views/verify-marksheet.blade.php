@php
    use App\Support\BengaliNumber;

    /**
     * @var \App\Models\Marksheet|null $marksheet
     * @var array{rows: \Illuminate\Support\Collection<int, array<string, mixed>>, summary: array<string, mixed>, updated_at: ?\Illuminate\Support\Carbon}|null $result
     */
    $student = $marksheet?->student;
    $exam = $marksheet?->exam;

    $formatMark = fn (float $mark): string => BengaliNumber::convert(rtrim(rtrim(number_format($mark, 2, '.', ''), '0'), '.'));
    $markText = fn (array $line): string => match (true) {
        (bool) $line['is_absent'] => 'অনুপস্থিত',
        $line['total_marks'] === null => '—',
        default => $formatMark((float) $line['total_marks']),
    };

    $details = $marksheet ? collect([
        ['শিক্ষার্থীর নাম', $student?->user?->name],
        ['স্টুডেন্ট আইডি', BengaliNumber::convert((string) $student?->idCardNumber())],
        ['শ্রেণি', $student?->class?->display_name],
        ['রোল নং', BengaliNumber::convert(sprintf('%02d', $student?->roll_no))],
        ['শাখা', $student?->section?->name],
        ['গ্রুপ', $student?->group?->display_name],
        ['পরীক্ষা', $exam?->examType?->name.' ('.BengaliNumber::convert((string) $exam?->session_label).')'],
    ])->filter(fn (array $detail): bool => filled($detail[1])) : collect();

    $summary = $result['summary'] ?? null;
    $summaryLines = $summary ? collect([
        ['প্রাপ্ত নম্বর', $formatMark((float) $summary['obtained_marks']).' / '.$formatMark((float) $summary['obtained_full_marks'])],
        ['জিপিএ', BengaliNumber::convert((string) $summary['gpa']).($summary['overall_grade_label'] ? " ({$summary['overall_grade_label']})" : '')],
        ['মোট জিপি', BengaliNumber::convert((string) $summary['total_grade_point'])],
        ['শ্রেণিতে মেধাক্রম', $summary['class_rank'] ? BengaliNumber::convert((string) $summary['class_rank']) : null],
        ['শাখায় মেধাক্রম', $summary['section_rank'] ? BengaliNumber::convert((string) $summary['section_rank']) : null],
    ])->filter(fn (array $line): bool => filled($line[1])) : collect();
@endphp
@extends('verify-layout')

@section('title', 'মার্কশিট যাচাই')

@section('wrapClass', 'wrap--wide')

@push('styles')
    <style>
        .section-title { margin: 1.25rem 0 .4rem; font-weight: 700; }
        .marks { border: 1px solid var(--line); border-radius: .6rem; overflow: hidden; }
        .marks th { background: var(--soft); color: var(--muted); font-weight: 600; font-size: .92em; }
        .marks th, .marks td { padding: .45rem .55rem; }
        .marks .num { text-align: center; white-space: nowrap; width: 18%; }
        .marks .paper { display: block; color: var(--muted); font-size: .9em; font-weight: 500; }
        .marks .grade { font-weight: 700; }
        .summary { margin-top: .75rem; border: 1px solid var(--line); border-radius: .6rem; overflow: hidden; }
        .summary th { width: 45%; color: var(--muted); font-weight: 500; padding-left: .55rem; }
        .summary td { font-weight: 700; padding-right: .55rem; }
        .meta { margin-top: .9rem; color: var(--muted); text-align: center; font-size: .9em; }
        .meta strong { color: var(--ink); letter-spacing: .08em; }
        .verify-link { color: var(--accent); font-weight: 600; }
    </style>
@endpush

@section('content')
    @if ($marksheet)
        @if ($result)
            <div class="status status--ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                <span>বৈধ মার্কশিট — স্কুল থেকে ইস্যু করা</span>
            </div>
        @else
            <div class="status status--warn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                <span>ফলাফল এখনো প্রকাশিত হয়নি</span>
            </div>
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

            @if ($result)
                <div class="section-title">বিষয়ভিত্তিক ফলাফল</div>
                <table class="marks">
                    <thead>
                        <tr>
                            <th>বিষয়</th>
                            <th class="num">নম্বর</th>
                            <th class="num">গ্রেড</th>
                            <th class="num">জিপি</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($result['rows'] as $row)
                            <tr>
                                <td>
                                    {{ $row['subject_name'] }}
                                    @foreach ($row['papers'] ?? [] as $paper)
                                        <span class="paper">{{ $paper['paper_label'] }} পত্র: {{ $markText($paper) }}</span>
                                    @endforeach
                                </td>
                                <td class="num">{{ $markText($row) }}</td>
                                <td class="num grade">{{ $row['grade_label'] ?? '—' }}</td>
                                <td class="num">{{ $row['grade_point'] === null ? '—' : BengaliNumber::convert(number_format((float) $row['grade_point'], 2)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <table class="summary">
                    @foreach ($summaryLines as [$label, $value])
                        <tr>
                            <th>{{ $label }}</th>
                            <td>{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>

                <p class="hint">মার্কশিটের নম্বর ও গ্রেড এই পেজের সঙ্গে মিলিয়ে দেখুন। কোনো অমিল থাকলে মার্কশিটটি সংশোধিত বা জাল হতে পারে — স্কুল অফিসে যোগাযোগ করুন।</p>
            @else
                <p class="hint">এই পরীক্ষার ফলাফল প্রকাশিত হলে এখানে বিষয়ভিত্তিক নম্বর দেখা যাবে।</p>
            @endif

            <p class="meta">
                যাচাই কোড: <strong>{{ $marksheet->formattedVerificationCode() }}</strong>
                @if ($result['updated_at'] ?? null)
                    <br>সর্বশেষ হালনাগাদ: {{ BengaliNumber::convert($result['updated_at']->format('d/m/Y')) }}
                @endif
            </p>
        </div>
    @else
        <div class="status status--bad">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
            <span>মার্কশিট পাওয়া যায়নি</span>
        </div>
        <div class="body">
            <p style="margin: 0;">এই কোডের কোনো মার্কশিট স্কুলের রেকর্ডে নেই। মার্কশিটটি জাল বা বাতিল হতে পারে — স্কুল অফিসে যোগাযোগ করুন।</p>
            <p class="meta"><a class="verify-link" href="{{ route('verify') }}">আবার কোড দিয়ে চেষ্টা করুন</a></p>
        </div>
    @endif
@endsection
