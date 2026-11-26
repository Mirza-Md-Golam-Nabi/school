@php
    use App\Support\BengaliNumber;
    use Carbon\Carbon;

    /**
     * @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\FeePayment> $payments  empty when the slip isn't (or no longer is) on record
     * @var \Illuminate\Support\Carbon|null $lastRevisedAt  set when a payment on the slip was edited after it was issued
     */
    $firstPayment = $payments->first();
    $student = $firstPayment?->student;

    $formatTaka = fn (float $amount): string => '৳ '.BengaliNumber::convert(number_format($amount, 2));
    $formatDate = fn (?Carbon $date): ?string => $date ? BengaliNumber::convert($date->format('d/m/Y')) : null;
    $period = function (\App\Models\FeePayment $payment): string {
        $invoice = $payment->invoice;

        if (! $invoice) {
            return '—';
        }

        return BengaliNumber::convert(($invoice->month ? Carbon::create()->month($invoice->month)->locale('bn')->translatedFormat('F').' ' : '').$invoice->year);
    };

    $details = $firstPayment ? collect([
        ['রসিদ নং', preg_replace('#/\d+$#', '', $firstPayment->receipt_no)],
        ['শিক্ষার্থীর নাম', $student?->user?->name],
        ['স্টুডেন্ট আইডি', BengaliNumber::convert((string) $student?->idCardNumber())],
        ['শ্রেণি', $student?->class?->display_name],
        ['রোল নং', $student ? BengaliNumber::convert(sprintf('%02d', $student->roll_no)) : null],
        ['শাখা', $student?->section?->name],
        ['গ্রুপ', $student?->group?->display_name],
        ['পরিশোধের তারিখ', $formatDate($firstPayment->payment_date)],
        ['পরিশোধের মাধ্যম', $firstPayment->payment_method?->getLabel()],
        ['গ্রহণকারী', $firstPayment->receivedBy?->name],
    ])->filter(fn (array $detail): bool => filled($detail[1])) : collect();
@endphp
@extends('verify-layout')

@section('title', 'রসিদ যাচাই')

@push('styles')
    <style>
        .section-title { margin: 1.25rem 0 .4rem; font-weight: 700; }
        .lines { border: 1px solid var(--line); border-radius: .6rem; overflow: hidden; }
        .lines th { background: var(--soft); color: var(--muted); font-weight: 600; font-size: .92em; }
        .lines th, .lines td { padding: .45rem .55rem; }
        .lines .num { text-align: right; white-space: nowrap; }
        .lines .total td { font-weight: 700; background: var(--soft); }
        .meta { margin-top: .9rem; color: var(--muted); text-align: center; font-size: .9em; }
        .meta strong { color: var(--ink); letter-spacing: .08em; }
        .verify-link { color: var(--accent); font-weight: 600; }
    </style>
@endpush

@section('content')
    @if ($firstPayment)
        <div class="status status--ok">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            <span>বৈধ রসিদ — স্কুলের হিসাবে জমা আছে</span>
        </div>

        @if ($lastRevisedAt)
            <div class="note note--warn">ইস্যুর পর এই রসিদের তথ্য সংশোধন করা হয়েছে ({{ $formatDate($lastRevisedAt) }})। নিচের পরিমাণই এখন স্কুলের রেকর্ডে আছে।</div>
        @endif

        <div class="body">
            <table class="details">
                @foreach ($details as [$label, $value])
                    <tr>
                        <th>{{ $label }}</th>
                        <td>{{ $value }}</td>
                    </tr>
                @endforeach
            </table>

            <div class="section-title">পরিশোধিত ফি</div>
            <table class="lines">
                <thead>
                    <tr>
                        <th>ফি-র ধরন</th>
                        <th>সময়কাল</th>
                        <th class="num">পরিমাণ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td>{{ $payment->invoice?->feeType?->name ?? '—' }}</td>
                            <td>{{ $period($payment) }}</td>
                            <td class="num">{{ $formatTaka((float) $payment->amount_paid) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td colspan="2">মোট পরিশোধ</td>
                        <td class="num">{{ $formatTaka((float) $payments->sum('amount_paid')) }}</td>
                    </tr>
                </tbody>
            </table>

            <p class="hint">রসিদের ফি ও টাকার পরিমাণ এই পেজের সঙ্গে মিলিয়ে দেখুন। কোনো অমিল থাকলে রসিদটি পুরনো, সংশোধিত বা জাল হতে পারে — স্কুল অফিসে যোগাযোগ করুন।</p>

            <p class="meta">যাচাই কোড: <strong>{{ $firstPayment->formattedVerificationCode() }}</strong></p>
        </div>
    @else
        <div class="status status--bad">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
            <span>রসিদ পাওয়া যায়নি</span>
        </div>
        <div class="body">
            <p style="margin: 0;">এই কোডের কোনো পেমেন্ট স্কুলের রেকর্ডে নেই। রসিদটি জাল বা বাতিল হতে পারে — স্কুল অফিসে যোগাযোগ করুন।</p>
            <p class="meta"><a class="verify-link" href="{{ route('verify') }}">আবার কোড দিয়ে চেষ্টা করুন</a></p>
        </div>
    @endif
@endsection
