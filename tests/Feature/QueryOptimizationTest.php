<?php

use App\Actions\Attendance\SaveClassAttendanceAction;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\StudentStatus;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Enums\UserType;
use App\Models\AccountTransaction;
use App\Models\Attendance;
use App\Models\Classes;
use App\Models\FundTransaction;
use App\Models\LeaveApplication;
use App\Models\SalaryInvoice;
use App\Models\SalaryPayment;
use App\Models\SchoolAccount;
use App\Models\SchoolSetting;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Support\ActivityLogLabelCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function makeQueryOptimizationStudent(int $classId, int $rollNo): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true, 'name' => "Student Roll {$rollNo}"]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => $rollNo,
        'current_class_id' => $classId,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

/**
 * @return Collection<int, string>
 */
function selectQueriesDuring(Closure $callback): Collection
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $callback();

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => str_starts_with(strtolower($query), 'select'))
        ->values();

    DB::disableQueryLog();

    return $queries;
}

it('indexes the foreign keys and filter columns the app queries by', function (string $table, array $columns) {
    expect(Schema::hasIndex($table, $columns))->toBeTrue();
})->with([
    'student profile → user' => ['student_profiles', ['user_id']],
    'class roster ordered by roll' => ['student_profiles', ['current_class_id', 'status', 'roll_no']],
    'teacher profile → user' => ['teacher_profiles', ['user_id']],
    'class attendance by day' => ['attendances', ['class_id', 'date']],
    'daily attendance overview' => ['attendances', ['attendable_type', 'date', 'status']],
    'payments of an invoice' => ['fee_payments', ['invoice_id']],
    'payable invoices' => ['student_fee_invoices', ['status']],
    'ledger entry by source' => ['account_transactions', ['source_type', 'source_id']],
    'payments of a salary invoice' => ['salary_payments', ['salary_invoice_id']],
    'exams of a class and session' => ['exams', ['class_id', 'session_year']],
    'results of a student' => ['student_results', ['student_id', 'exam_id']],
]);

it('saves class attendance without one lookup query per student', function () {
    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $students = collect(range(1, 6))->map(fn (int $roll) => makeQueryOptimizationStudent($class->id, $roll));
    $marker = User::factory()->create();
    $date = now()->toDateString();

    $roster = StudentProfile::with('user')->whereIn('id', $students->pluck('id'))->orderBy('roll_no')->get();
    $presentIds = $roster->take(3)->pluck('id')->map(fn ($id) => (string) $id)->all();

    $queries = selectQueriesDuring(fn () => app(SaveClassAttendanceAction::class)
        ->handle($class->id, $class, $date, $roster, $presentIds, $marker->id, $marker->name));

    // Lookups by person — not the by-ID reloads done for queued notifications.
    $isAttendanceLookup = fn (string $query): bool => str_contains($query, 'from "attendances"')
        && str_contains($query, '"attendable_type"');

    expect($queries->filter($isAttendanceLookup))->toHaveCount(1)
        ->and(Attendance::where('date', $date)->where('status', AttendanceStatus::Present)->count())->toBe(3)
        ->and(Attendance::where('date', $date)->where('status', AttendanceStatus::Absent)->count())->toBe(3);

    // Saving the same list again updates nothing and still reads the rows once.
    $queries = selectQueriesDuring(fn () => app(SaveClassAttendanceAction::class)
        ->handle($class->id, $class, $date, $roster, $presentIds, $marker->id, $marker->name));

    expect($queries->filter($isAttendanceLookup))->toHaveCount(1)
        ->and(Attendance::where('date', $date)->count())->toBe(6);
});

it('resolves the class name once when logging many attendance rows', function () {
    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $students = collect(range(1, 5))->map(fn (int $roll) => makeQueryOptimizationStudent($class->id, $roll));

    // Creating the students already cached the class label — start clean.
    app(ActivityLogLabelCache::class)->flush();

    $queries = selectQueriesDuring(function () use ($students, $class): void {
        foreach ($students as $student) {
            Attendance::create([
                'attendable_type' => StudentProfile::class,
                'attendable_id' => $student->id,
                'class_id' => $class->id,
                'date' => '2026-03-01',
                'status' => AttendanceStatus::Present,
                'source' => AttendanceSource::Manual,
            ]);
        }
    });

    expect($queries->filter(fn (string $query): bool => str_contains($query, 'from "classes"')))->toHaveCount(1)
        ->and(Activity::where('log_name', 'student_attendance')->count())->toBe(5);
});

it('logs the new name after a labelled record is renamed in the same request', function () {
    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $subject = Subject::create(['name' => 'Bangla', 'is_active' => true]);
    $first = makeQueryOptimizationStudent($class->id, 1);
    $second = makeQueryOptimizationStudent($class->id, 2);

    $mark = fn (StudentProfile $student) => Attendance::create([
        'attendable_type' => StudentProfile::class,
        'attendable_id' => $student->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'date' => '2026-03-01',
        'status' => AttendanceStatus::Present,
        'source' => AttendanceSource::Manual,
    ]);

    $mark($first);
    $class->update(['name' => 'Class One (New)']);
    $mark($second);

    $descriptions = Activity::where('log_name', 'student_attendance')->orderBy('id')->pluck('description');

    expect($descriptions[0])->toContain('"Class One"')
        ->and($descriptions[1])->toContain('"Class One (New)"');
});

it('reads all school settings with a single query and sees later changes', function () {
    SchoolSetting::set('school_name', 'Ideal School');
    SchoolSetting::set('school_address', 'Dhaka');

    $queries = selectQueriesDuring(function (): void {
        SchoolSetting::get('school_name');
        SchoolSetting::get('school_address');
        SchoolSetting::get('missing_key', 'fallback');
    });

    expect($queries)->toHaveCount(1)
        ->and(SchoolSetting::get('school_name'))->toBe('Ideal School')
        ->and(SchoolSetting::get('missing_key', 'fallback'))->toBe('fallback');

    SchoolSetting::set('school_name', 'Model School');

    expect(SchoolSetting::get('school_name'))->toBe('Model School');
});

it('computes paid and due amounts of salary invoices from eager-loaded data', function () {
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 100000]);
    $teacher = TeacherProfile::factory()->create();

    $invoices = collect([1, 2, 3])->map(fn (int $month) => SalaryInvoice::create([
        'invoice_no' => "SAL-2026-0{$month}-0001",
        'profileable_type' => TeacherProfile::class,
        'profileable_id' => $teacher->id,
        'month' => $month,
        'year' => 2026,
        'gross_amount' => 1000,
        'deduction_amount' => 0,
        'net_amount' => 1000,
        'status' => InvoiceStatus::Unpaid,
        'is_manual' => true,
    ]));

    SalaryPayment::create([
        'salary_invoice_id' => $invoices[0]->id,
        'amount_paid' => 400,
        'payment_method' => PaymentMethod::cases()[0],
        'payment_date' => '2026-03-05',
        'school_account_id' => $account->id,
    ]);

    $withSum = SalaryInvoice::withSum('payments', 'amount_paid')->withExists('payments')->orderBy('month')->get();
    $withRelation = SalaryInvoice::with('payments')->orderBy('month')->get();

    $queries = selectQueriesDuring(function () use ($withSum, $withRelation): void {
        foreach ([$withSum, $withRelation] as $loaded) {
            expect($loaded->map(fn (SalaryInvoice $invoice): float => $invoice->due_amount)->all())
                ->toBe([600.0, 1000.0, 1000.0]);
        }

        expect($withSum->map(fn (SalaryInvoice $invoice): bool => $invoice->isLocked())->all())
            ->toBe([true, false, false]);
    });

    expect($queries)->toBeEmpty()
        ->and(SalaryInvoice::find($invoices[0]->id)->total_paid)->toBe(400.0)
        ->and(SalaryInvoice::find($invoices[1]->id)->isLocked())->toBeFalse();
});

it('resolves ledger party labels for a page of transactions in one batch', function () {
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 0]);
    $category = TransactionCategory::create(['name' => 'Donation', 'type' => TransactionType::Income, 'is_active' => true]);

    foreach (['Rahim Traders', 'Karim Store', 'Alumni Association'] as $party) {
        FundTransaction::create([
            'transaction_category_id' => $category->id,
            'school_account_id' => $account->id,
            'type' => TransactionType::Income,
            'title' => "Donation from {$party}",
            'amount' => 500,
            'transaction_date' => '2026-03-01',
            'party_name' => $party,
        ]);
    }

    $transactions = AccountTransaction::where('source_type', TransactionSource::Other)->orderBy('id')->get();

    expect($transactions)->toHaveCount(3);

    $labels = [];
    $queries = selectQueriesDuring(function () use ($transactions, &$labels): void {
        $labels = AccountTransaction::resolvePartyLabels($transactions);
    });

    expect($queries)->toHaveCount(1)
        ->and(array_values($labels))->toBe(['Rahim Traders', 'Karim Store', 'Alumni Association'])
        ->and($transactions->first()->resolvePartyLabel())->toBe('Rahim Traders');
});

it('counts absent days after a leave with a single attendance query', function () {
    $this->travelTo('2026-03-20 10:00:00');

    $teacher = TeacherProfile::factory()->create();

    $leave = LeaveApplication::factory()->create([
        'applicant_type' => TeacherProfile::class,
        'applicant_id' => $teacher->id,
        'from_date' => '2026-03-01',
        'to_date' => '2026-03-05',
        'total_days' => 5,
        'status' => 'approved',
    ]);

    // Back at work on the 10th → absent on the 6th, 7th, 8th and 9th.
    Attendance::create([
        'attendable_type' => TeacherProfile::class,
        'attendable_id' => $teacher->id,
        'date' => '2026-03-10',
        'status' => AttendanceStatus::Present,
        'source' => AttendanceSource::Manual,
    ]);

    $queries = selectQueriesDuring(fn () => $this->artisan('hr:check-leave-excess')->assertSuccessful());

    expect($queries->filter(fn (string $query): bool => str_contains($query, 'from "attendances"')))->toHaveCount(1)
        ->and($leave->excessLog()->first()?->excess_days)->toBe(4);
});
