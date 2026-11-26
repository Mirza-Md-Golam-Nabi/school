<?php

use App\Actions\AttachClassDocumentStatsAction;
use App\Actions\AttachClassFeeDiscountStatsAction;
use App\Actions\AttachClassFeeInvoiceStatsAction;
use App\Actions\AttachClassFeePaymentStatsAction;
use App\Enums\FeeDiscountType;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\StudentFeeInvoices\Pages\ListStudentFeeInvoices;
use App\Models\AdmitCard;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\FeeDiscount;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\StudentFeeDiscount;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createCardStatsClass(string $name, int $order): Classes
{
    return Classes::create(['name' => $name, 'order' => $order, 'is_active' => true]);
}

function createCardStatsStudent(Classes $class, StudentStatus $status = StudentStatus::Active): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student])->id,
        'roll_no' => random_int(1, 100000),
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => $status,
    ]);
}

function createCardStatsInvoice(StudentProfile $student, InvoiceStatus $status, float $amount): StudentFeeInvoice
{
    return StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => FeeType::create(['name' => 'Fee '.str()->random(6), 'is_active' => true])->id,
        'month' => now()->month,
        'year' => now()->year,
        'original_amount' => $amount,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => $amount,
        'status' => $status,
    ]);
}

it('attaches each class its own invoice stats from a single grouped query', function () {
    $classA = createCardStatsClass('Class A', 1);
    $classB = createCardStatsClass('Class B', 2);
    $emptyClass = createCardStatsClass('Class C', 3);

    $studentA = createCardStatsStudent($classA);
    createCardStatsInvoice($studentA, InvoiceStatus::Unpaid, 500);
    createCardStatsInvoice($studentA, InvoiceStatus::Partial, 300);
    createCardStatsInvoice($studentA, InvoiceStatus::Paid, 1000);

    $studentB = createCardStatsStudent($classB);
    createCardStatsInvoice($studentB, InvoiceStatus::Unpaid, 200);

    $trashedStudent = createCardStatsStudent($classB);
    createCardStatsInvoice($trashedStudent, InvoiceStatus::Unpaid, 9999);
    $trashedStudent->delete();

    $loadedClasses = Classes::orderBy('order')->get();

    DB::enableQueryLog();
    $classes = app(AttachClassFeeInvoiceStatsAction::class)->handle($loadedClasses)->keyBy('id');
    $queryCount = count(DB::getQueryLog());

    expect($queryCount)->toBe(1)
        ->and($classes[$classA->id]->total_invoices)->toBe(3)
        ->and((float) $classes[$classA->id]->outstanding_amount)->toBe(800.0)
        ->and($classes[$classA->id]->invoiceStats->get(InvoiceStatus::Paid->value)->count)->toBe(1)
        ->and($classes[$classB->id]->total_invoices)->toBe(1)
        ->and((float) $classes[$classB->id]->outstanding_amount)->toBe(200.0)
        ->and($classes[$emptyClass->id]->total_invoices)->toBe(0)
        ->and($classes[$emptyClass->id]->invoiceStats)->toBeEmpty();
});

it('attaches each class its own payment stats keyed by payment method', function () {
    $classA = createCardStatsClass('Class A', 1);
    $classB = createCardStatsClass('Class B', 2);

    $studentA = createCardStatsStudent($classA);
    $studentB = createCardStatsStudent($classB);

    foreach ([[$studentA, 300, PaymentMethod::Cash], [$studentA, 200, PaymentMethod::Cash], [$studentB, 700, PaymentMethod::Cash]] as $i => [$student, $amount, $method]) {
        FeePayment::create([
            'receipt_no' => "RCP-CARD-{$i}",
            'student_id' => $student->id,
            'invoice_id' => createCardStatsInvoice($student, InvoiceStatus::Partial, 1000)->id,
            'amount_paid' => $amount,
            'payment_method' => $method,
            'payment_date' => today(),
        ]);
    }

    $classes = app(AttachClassFeePaymentStatsAction::class)->handle(Classes::orderBy('order')->get())->keyBy('id');

    expect($classes[$classA->id]->total_payments)->toBe(2)
        ->and((float) $classes[$classA->id]->total_collected)->toBe(500.0)
        ->and($classes[$classA->id]->paymentStats->keys()->all())->toBe([PaymentMethod::Cash->value])
        ->and($classes[$classB->id]->total_payments)->toBe(1)
        ->and((float) $classes[$classB->id]->total_collected)->toBe(700.0);
});

it('counts each class generated and pending admit cards', function () {
    $classA = createCardStatsClass('Class A', 1);
    $classB = createCardStatsClass('Class B', 2);

    $exam = Exam::create([
        'exam_type_id' => ExamType::create(['name' => 'Half Yearly', 'is_active' => true])->id,
        'class_id' => $classA->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
    ]);

    AdmitCard::create(['student_id' => createCardStatsStudent($classA)->id, 'exam_id' => $exam->id, 'is_generated' => true]);
    AdmitCard::create(['student_id' => createCardStatsStudent($classA)->id, 'exam_id' => $exam->id, 'is_generated' => false]);
    AdmitCard::create(['student_id' => createCardStatsStudent($classA)->id, 'exam_id' => $exam->id, 'is_generated' => false]);

    $classes = app(AttachClassDocumentStatsAction::class)
        ->handle(Classes::orderBy('order')->get(), AdmitCard::class, 'admitCardStats', 'total_admit_cards')
        ->keyBy('id');

    expect($classes[$classA->id]->total_admit_cards)->toBe(3)
        ->and($classes[$classA->id]->pending_count)->toBe(2)
        ->and($classes[$classA->id]->admitCardStats->get('generated'))->toBe(1)
        ->and($classes[$classB->id]->total_admit_cards)->toBe(0)
        ->and($classes[$classB->id]->pending_count)->toBe(0);
});

it('counts distinct active students per discount for each class', function () {
    $classA = createCardStatsClass('Class A', 1);
    $classB = createCardStatsClass('Class B', 2);

    $sibling = FeeDiscount::create(['name' => 'Sibling', 'discount_type' => FeeDiscountType::Percent, 'discount_value' => 10]);
    $merit = FeeDiscount::create(['name' => 'Merit', 'discount_type' => FeeDiscountType::Percent, 'discount_value' => 20]);

    $give = function (StudentProfile $student, FeeDiscount $discount): void {
        StudentFeeDiscount::create([
            'student_id' => $student->id,
            'fee_type_id' => FeeType::create(['name' => 'Fee '.str()->random(6), 'is_active' => true])->id,
            'discount_id' => $discount->id,
            'session_year' => now()->year,
        ]);
    };

    $studentA = createCardStatsStudent($classA);
    $give($studentA, $sibling);
    $give($studentA, $sibling);
    $give(createCardStatsStudent($classA), $sibling);
    $give(createCardStatsStudent($classA), $merit);
    $give(createCardStatsStudent($classA, StudentStatus::Inactive), $merit);
    $give(createCardStatsStudent($classB), $merit);

    $classes = app(AttachClassFeeDiscountStatsAction::class)->handle(Classes::orderBy('order')->get())->keyBy('id');

    expect($classes[$classA->id]->discountStats->map(fn (object $stat): array => [$stat->name, $stat->student_count])->all())
        ->toBe([['Sibling', 2], ['Merit', 1]])
        ->and($classes[$classB->id]->discountStats->pluck('student_count')->all())->toBe([1]);
});

it('keeps the class card stats after a later Livewire request', function () {
    Role::findOrCreate('super-admin', 'web');
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $admin->assignRole('super-admin');
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');

    $class = createCardStatsClass('Class Seven', 1);
    createCardStatsInvoice(createCardStatsStudent($class), InvoiceStatus::Unpaid, 4321);

    Livewire::test(ListStudentFeeInvoices::class)
        ->assertViewHas('classes', fn ($classes): bool => $classes->first()->total_invoices === 1)
        ->call('$refresh')
        ->assertViewHas('classes', fn ($classes): bool => $classes->first()->total_invoices === 1
            && (float) $classes->first()->outstanding_amount === 4321.0);
});
