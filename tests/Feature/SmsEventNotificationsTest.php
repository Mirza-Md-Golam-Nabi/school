<?php

use App\Actions\Attendance\SaveClassAttendanceAction;
use App\Actions\GenerateMonthlyFeeInvoicesAction;
use App\Actions\ProcessFeePaymentAction;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\NoticeTargetType;
use App\Enums\PaymentMethod;
use App\Enums\SmsEvent;
use App\Enums\SmsStatus;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Jobs\PublishNoticeJob;
use App\Models\Classes;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\Notice;
use App\Models\NoticeTarget;
use App\Models\SchoolSetting;
use App\Models\SmsLog;
use App\Models\SmsSetting;
use App\Models\SmsVendor;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Http::preventStrayRequests();
    SchoolSetting::set('school_name', 'Ideal School');
    SmsVendor::factory()->create();
});

/**
 * @param  list<SmsEvent>  $events
 */
function enableSmsEvents(array $events, float $balance = 100): void
{
    $setting = SmsSetting::current();
    $setting->fill(['is_enabled' => true, 'rate_per_sms' => 0.25, 'enabled_events' => array_map(fn (SmsEvent $event) => $event->value, $events)])->save();
    $setting->forceFill(['balance' => $balance])->save();
}

function createSmsTestStudent(?Classes $class, ?string $guardianPhone, string $name = 'Rahim', ?string $ownPhone = null): StudentProfile
{
    $user = User::factory()->create(['name' => $name, 'phone' => $ownPhone, 'user_type' => UserType::Student, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => random_int(1, 100000),
        'current_class_id' => $class?->id,
        'guardian_phone' => $guardianPhone,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function saveSmsTestAttendance(Classes $class, StudentProfile $student, bool $isPresent, ?string $date = null): void
{
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    app(SaveClassAttendanceAction::class)->handle(
        classId: $class->id,
        class: $class,
        date: $date ?? now()->toDateString(),
        students: collect([$student]),
        presentIds: $isPresent ? [(string) $student->id] : [],
        markedBy: $admin->id,
        markedByName: $admin->name,
    );
}

it('texts the guardian when a student is marked absent today', function () {
    enableSmsEvents([SmsEvent::AttendanceAbsent]);
    $class = Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]);
    $student = createSmsTestStudent($class, '01711111111');

    saveSmsTestAttendance($class, $student, isPresent: false);

    expect(SmsLog::sole())
        ->phone->toBe('8801711111111')
        ->event->toBe(SmsEvent::AttendanceAbsent)
        ->status->toBe(SmsStatus::Pending)
        ->message->toBe('প্রিয় অভিভাবক, Rahim '.now()->format('d/m/Y').' তারিখে স্কুলে অনুপস্থিত। - Ideal School');
});

it('does not text for present students unless that event is switched on', function () {
    enableSmsEvents([SmsEvent::AttendanceAbsent]);
    $class = Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]);

    saveSmsTestAttendance($class, createSmsTestStudent($class, '01711111111'), isPresent: true);

    expect(SmsLog::count())->toBe(0);
});

it('does not text the guardian about a backdated attendance correction', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    enableSmsEvents([SmsEvent::AttendanceAbsent]);
    $class = Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]);

    saveSmsTestAttendance($class, createSmsTestStudent($class, '01711111111'), isPresent: false, date: now()->subDays(3)->toDateString());

    expect(SmsLog::count())->toBe(0);
});

it('uses the admin template and falls back to the student own number', function () {
    enableSmsEvents([SmsEvent::AttendancePresent]);
    SmsSetting::current()->update(['templates' => [SmsEvent::AttendancePresent->value => '{name} present ({class})']]);
    $class = Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]);

    saveSmsTestAttendance($class, createSmsTestStudent($class, null, 'Karim', '01822222222'), isPresent: true);

    expect(SmsLog::sole())->phone->toBe('8801822222222')->message->toBe('Karim present (Class 5)');
});

it('sends one fee SMS per student with the total of all generated invoices', function () {
    enableSmsEvents([SmsEvent::FeeInvoiceGenerated]);
    $class = Classes::create(['name' => 'Class One', 'order' => 1]);

    foreach ([['Tuition Fee', 500], ['Computer Fee', 200]] as [$name, $amount]) {
        FeeStructure::create([
            'class_id' => $class->id,
            'fee_type_id' => FeeType::create(['name' => $name, 'is_monthly' => true, 'is_active' => true])->id,
            'amount' => $amount,
            'due_day' => 10,
            'session_year' => now()->year,
            'is_active' => true,
        ]);
    }

    createSmsTestStudent($class, '01711111111', 'Rahim');
    createSmsTestStudent($class, '01822222222', 'Karim');

    app(GenerateMonthlyFeeInvoicesAction::class)->handle(now()->month, now()->year);

    expect(SmsLog::count())->toBe(2)
        ->and(SmsLog::where('phone', '8801711111111')->sole()->message)->toContain('700 টাকা')->toContain('Tuition Fee')->toContain('Computer Fee');
});

it('does not text about generated fees when that event is off', function () {
    enableSmsEvents([SmsEvent::FeePaymentReceived]);
    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    FeeStructure::create([
        'class_id' => $class->id,
        'fee_type_id' => FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true])->id,
        'amount' => 500,
        'due_day' => 10,
        'session_year' => now()->year,
        'is_active' => true,
    ]);
    createSmsTestStudent($class, '01711111111');

    app(GenerateMonthlyFeeInvoicesAction::class)->handle(now()->month, now()->year);

    expect(SmsLog::count())->toBe(0);
});

it('sends one payment SMS per receipt however many invoices it pays', function () {
    enableSmsEvents([SmsEvent::FeePaymentReceived]);
    $student = createSmsTestStudent(null, '01711111111');
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);

    $invoices = collect([1, 2])->map(fn (int $month) => StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'month' => $month,
        'year' => now()->year,
        'original_amount' => 300,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => 300,
        'status' => InvoiceStatus::Unpaid,
    ]));

    app(ProcessFeePaymentAction::class)->handle([
        'invoice_ids' => $invoices->pluck('id')->all(),
        'amount_paid' => 600,
        'receipt_no' => 'RCPT-77',
        'student_id' => $student->id,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
    ]);

    expect(SmsLog::sole())
        ->event->toBe(SmsEvent::FeePaymentReceived)
        ->message->toContain('600 টাকা')->toContain('RCPT-77');
});

it('texts the guardians of a class when a notice with SMS publishes', function () {
    enableSmsEvents([SmsEvent::Notice]);
    $classA = Classes::create(['name' => 'Class A', 'order' => 1]);
    $classB = Classes::create(['name' => 'Class B', 'order' => 2]);

    createSmsTestStudent($classA, '01711111111');
    createSmsTestStudent($classA, '01711111111');
    createSmsTestStudent($classA, '01822222222');
    createSmsTestStudent($classB, '01933333333');

    $notice = Notice::create([
        'title' => 'Exam routine published',
        'body' => '<p>See the board</p>',
        'target_type' => NoticeTargetType::ByClass,
        'send_sms' => true,
        'published_at' => now()->subMinute(),
        'created_by' => User::factory()->create()->id,
    ]);
    NoticeTarget::create(['notice_id' => $notice->id, 'targetable_type' => Classes::class, 'targetable_id' => $classA->id]);

    (new PublishNoticeJob($notice->id))->handle();

    // Two siblings share a guardian number — that guardian gets the notice once.
    expect(SmsLog::pluck('phone')->sort()->values()->all())->toBe(['8801711111111', '8801822222222'])
        ->and(SmsLog::first()->message)->toBe('Ideal School: নতুন নোটিশ - Exam routine published');
});

it('does not text a notice that did not ask for SMS', function () {
    enableSmsEvents([SmsEvent::Notice]);
    createSmsTestStudent(null, '01711111111');

    $notice = Notice::create([
        'title' => 'Holiday',
        'body' => '<p>Closed</p>',
        'target_type' => NoticeTargetType::Students,
        'send_sms' => false,
        'published_at' => now()->subMinute(),
        'created_by' => User::factory()->create()->id,
    ]);

    (new PublishNoticeJob($notice->id))->handle();

    expect(SmsLog::count())->toBe(0);
});
