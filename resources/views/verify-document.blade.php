@php
    /**
     * @var string $code  what was typed in, kept in the box after a miss
     * @var bool $notFound
     */
@endphp
@extends('verify-layout')

@section('title', 'ডকুমেন্ট যাচাই')

@push('styles')
    <style>
        .lead { margin: 0 0 .9rem; color: var(--muted); }
        label { display: block; margin-bottom: .35rem; font-weight: 600; }
        .row { display: flex; gap: .5rem; }
        input[type="text"] {
            flex: 1; min-width: 0; padding: .6rem .75rem; border: 1px solid var(--line); border-radius: .6rem;
            background: var(--soft); color: var(--ink); font: inherit; font-size: 1.1em; font-weight: 600;
            letter-spacing: .12em; text-transform: uppercase;
        }
        input[type="text"]:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
        button {
            flex: none; padding: .6rem 1rem; border: 0; border-radius: .6rem; background: var(--accent);
            color: #fff; font: inherit; font-weight: 700; cursor: pointer;
        }
    </style>
@endpush

@section('content')
    @if ($notFound)
        <div class="status status--bad">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
            <span>এই কোডের কোনো মার্কশিট পাওয়া যায়নি</span>
        </div>
    @endif

    <form class="body" method="GET" action="{{ route('verify') }}">
        <p class="lead">মার্কশিটের QR কোডের নিচে লেখা যাচাই কোডটি দিন। স্কুলের রেকর্ডে থাকা আসল ফলাফল দেখানো হবে।</p>

        <label for="code">যাচাই কোড</label>
        <div class="row">
            <input type="text" id="code" name="code" value="{{ $code }}" placeholder="K7M4-P9QX" maxlength="20" autocomplete="off" autocapitalize="characters" spellcheck="false" required autofocus>
            <button type="submit">যাচাই করুন</button>
        </div>

        @if ($notFound)
            <p class="hint">কোডটি ঠিকমতো লেখা হয়েছে কিনা দেখুন। তারপরও না পেলে মার্কশিটটি জাল বা বাতিল হতে পারে — স্কুল অফিসে যোগাযোগ করুন।</p>
        @endif
    </form>
@endsection
