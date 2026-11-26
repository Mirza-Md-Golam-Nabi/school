@php
    use App\Enums\EmploymentStatus;
    use App\Enums\StudentStatus;
    use App\Models\StaffProfile;
    use App\Models\StudentProfile;
    use App\Models\TeacherProfile;
    use App\Support\BengaliNumber;

    /** @var StudentProfile|TeacherProfile|StaffProfile|null $profile */
    $isStudent = $profile instanceof StudentProfile;

    $role = match (true) {
        $profile instanceof StudentProfile => 'শিক্ষার্থী',
        $profile instanceof TeacherProfile => 'শিক্ষক',
        $profile instanceof StaffProfile => 'স্টাফ',
        default => null,
    };

    $isActive = $profile && ! $profile->trashed()
        && in_array($profile->status, [StudentStatus::Active, EmploymentStatus::Active], true);

    $statusNote = match ($profile?->status) {
        StudentStatus::Transferred => 'এই শিক্ষার্থী অন্য প্রতিষ্ঠানে চলে গেছে (TC নিয়েছে)।',
        StudentStatus::Graduated => 'এই শিক্ষার্থী পড়াশোনা শেষ করে প্রতিষ্ঠান ছেড়েছে।',
        StudentStatus::Dropped, StudentStatus::Inactive => 'এই শিক্ষার্থী বর্তমানে প্রতিষ্ঠানে সক্রিয় নয়।',
        EmploymentStatus::Resigned => 'ইনি চাকরি থেকে অব্যাহতি নিয়েছেন।',
        EmploymentStatus::Retired => 'ইনি অবসরে গেছেন।',
        EmploymentStatus::Terminated => 'ইনি আর এই প্রতিষ্ঠানে কর্মরত নন।',
        default => 'ইনি বর্তমানে এই প্রতিষ্ঠানে সক্রিয় নন।',
    };

    $photoUrl = $isStudent ? $profile->photoUrl() : $profile?->user?->getFilamentAvatarUrl();

    $details = match (true) {
        $isStudent => collect([
            ['নাম', $profile->user?->name],
            ['স্টুডেন্ট আইডি', $profile->idCardNumber()],
            ['শ্রেণি', $isActive ? $profile->class?->display_name : null],
            ['শাখা', $isActive ? $profile->section?->name : null],
            ['গ্রুপ', $isActive ? $profile->group?->display_name : null],
            ['রোল নং', $isActive && $profile->roll_no ? BengaliNumber::convert(sprintf('%02d', $profile->roll_no)) : null],
        ]),
        $profile !== null => collect([
            ['নাম', $profile->user?->name],
            ['আইডি', $profile->idCardNumber()],
            ['পদবি', $profile->designation],
            ['বিভাগ', $profile instanceof TeacherProfile ? $profile->department : null],
        ]),
        default => collect(),
    };

    $details = $details->filter(fn (array $detail): bool => filled($detail[1]));
@endphp
@extends('verify-layout')

@section('title', 'আইডি কার্ড যাচাই')

@section('content')
    @if ($profile)
        @if ($isActive)
            <div class="status status--ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                <span>বৈধ আইডি কার্ড — বর্তমান {{ $role }}</span>
            </div>
        @else
            <div class="status status--bad">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                <span>কার্ডটি আর বৈধ নয়</span>
            </div>
            <div class="note note--bad">{{ $statusNote }}</div>
        @endif

        <div class="body">
            @if ($photoUrl)
                <img class="photo" src="{{ $photoUrl }}" alt="ছবি">
            @else
                <div class="photo photo--empty">ছবি নেই</div>
            @endif

            <table class="details">
                <tr>
                    <th>পরিচয়</th>
                    <td>{{ $role }}</td>
                </tr>
                @foreach ($details as [$label, $value])
                    <tr>
                        <th>{{ $label }}</th>
                        <td>{{ $value }}</td>
                    </tr>
                @endforeach
            </table>

            <p class="hint">কার্ডের ছবি ও তথ্য এই পেজের সঙ্গে মিলিয়ে দেখুন। কার্ডটি কোথাও পাওয়া গেলে স্কুল অফিসে জমা দিন।</p>
        </div>
    @else
        <div class="status status--bad">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
            <span>আইডি কার্ড পাওয়া যায়নি</span>
        </div>
        <div class="body">
            <p style="margin: 0;">এই QR কোডের কোনো আইডি কার্ড স্কুলের রেকর্ডে নেই। কার্ডটি জাল হতে পারে, অথবা হারানোর পর বাতিল করা হয়েছে। স্কুল অফিসে যোগাযোগ করুন।</p>
        </div>
    @endif
@endsection
