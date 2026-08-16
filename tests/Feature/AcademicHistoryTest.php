<?php

use App\Enums\ExamConfigType;
use App\Enums\Gender;
use App\Enums\PaymentMethod;
use App\Enums\PromotionStatus;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Student\Pages\AcademicHistory;
use App\Filament\Student\Pages\AcademicHistoryClass;
use App\Filament\Student\Resources\ExamResults\ExamResultResource;
use App\Filament\Student\Resources\FeeInvoices\Pages\AcademicHistoryFees;
use App\Models\Attendance;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\ExamTypeConfig;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\StudentClassHistory;
use App\Models\StudentFeeInvoice;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('student');
});

function createAcademicHistoryTestStudent(?int $classId = null): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => random_int(1, 999),
        'current_class_id' => $classId,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

it("only lists the logged-in student's own class history, newest session first", function () {
    $classFive = Classes::create(['name' => 'Class Five', 'order' => 5]);
    $classSix = Classes::create(['name' => 'Class Six', 'order' => 6]);

    $me = createAcademicHistoryTestStudent($classSix->id);
    $otherStudent = createAcademicHistoryTestStudent($classSix->id);

    StudentClassHistory::create([
        'student_id' => $me->id,
        'class_id' => $classFive->id,
        'roll_no' => 3,
        'session_year' => 2025,
        'status' => PromotionStatus::Promoted,
    ]);
    StudentClassHistory::create([
        'student_id' => $me->id,
        'class_id' => $classSix->id,
        'roll_no' => 2,
        'session_year' => 2026,
        'status' => PromotionStatus::Promoted,
    ]);
    StudentClassHistory::create([
        'student_id' => $otherStudent->id,
        'class_id' => $classFive->id,
        'roll_no' => 1,
        'session_year' => 2025,
        'status' => PromotionStatus::Promoted,
    ]);

    $this->actingAs($me->user);

    $page = new AcademicHistory;
    $histories = $page->getHistories();

    expect($histories)->toHaveCount(2)
        ->and($histories->first()->session_year)->toBe(2026)
        ->and($histories->last()->session_year)->toBe(2025)
        ->and($histories->pluck('student_id')->unique()->all())->toBe([$me->id]);
});

it('404s when the requested history record does not belong to the logged-in student', function () {
    $class = Classes::create(['name' => 'Class Five', 'order' => 5]);
    $me = createAcademicHistoryTestStudent();
    $otherStudent = createAcademicHistoryTestStudent();

    $othersHistory = StudentClassHistory::create([
        'student_id' => $otherStudent->id,
        'class_id' => $class->id,
        'roll_no' => 1,
        'session_year' => 2025,
        'status' => PromotionStatus::Promoted,
    ]);

    $this->actingAs($me->user);

    expect(fn () => Livewire::test(AcademicHistoryClass::class, ['history' => $othersHistory->id]))
        ->toThrow(ModelNotFoundException::class);
});

it('summarizes attendance as present days, working days, and rank among that class-year\'s classmates', function () {
    $class = Classes::create(['name' => 'Class Five', 'order' => 5]);
    $me = createAcademicHistoryTestStudent();
    $classmate = createAcademicHistoryTestStudent();

    $history = StudentClassHistory::create([
        'student_id' => $me->id,
        'class_id' => $class->id,
        'roll_no' => 1,
        'session_year' => 2025,
        'status' => PromotionStatus::Promoted,
    ]);
    StudentClassHistory::create([
        'student_id' => $classmate->id,
        'class_id' => $class->id,
        'roll_no' => 2,
        'session_year' => 2025,
        'status' => PromotionStatus::Promoted,
    ]);

    $mark = function (StudentProfile $student, string $date, string $status) use ($class): void {
        Attendance::insert([
            'attendable_type' => StudentProfile::class,
            'attendable_id' => $student->id,
            'date' => $date,
            'status' => $status,
            'source' => 'manual',
            'class_id' => $class->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    };

    // 4 working days in 2025; I attended 2 (top of the ranking), classmate attended 1.
    $mark($me, '2025-01-05', 'present');
    $mark($me, '2025-01-06', 'present');
    $mark($me, '2025-01-07', 'absent');
    $mark($classmate, '2025-01-08', 'present');

    // A different year must not leak into this class-year's totals.
    $mark($me, '2026-01-05', 'present');

    $this->actingAs($me->user);

    $page = Livewire::test(AcademicHistoryClass::class, ['history' => $history->id])->instance();
    $summary = $page->getAttendanceSummary();

    expect($summary['workingDays'])->toBe(4)
        ->and($summary['presentDays'])->toBe(2)
        ->and($summary['rank'])->toBe(1)
        ->and($summary['totalStudents'])->toBe(2);
});

it("totals only the student's own paid amount for that class-year and links to the filtered fee page", function () {
    $class = Classes::create(['name' => 'Class Five', 'order' => 5]);
    $me = createAcademicHistoryTestStudent();

    $history = StudentClassHistory::create([
        'student_id' => $me->id,
        'class_id' => $class->id,
        'roll_no' => 1,
        'session_year' => 2025,
        'status' => PromotionStatus::Promoted,
    ]);

    $feeType = FeeType::create(['name' => 'Tuition', 'is_active' => true]);

    $paidInvoice = StudentFeeInvoice::create([
        'student_id' => $me->id,
        'fee_type_id' => $feeType->id,
        'month' => 1,
        'year' => 2025,
        'original_amount' => 1000,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => 1000,
        'status' => 'paid',
    ]);
    FeePayment::create([
        'receipt_no' => 'RCP-2025',
        'student_id' => $me->id,
        'invoice_id' => $paidInvoice->id,
        'amount_paid' => 1000,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => '2025-01-10',
    ]);

    // A different year's invoice must not count toward this class-year's total.
    $otherYearInvoice = StudentFeeInvoice::create([
        'student_id' => $me->id,
        'fee_type_id' => $feeType->id,
        'month' => 1,
        'year' => 2026,
        'original_amount' => 500,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => 500,
        'status' => 'paid',
    ]);
    FeePayment::create([
        'receipt_no' => 'RCP-2026',
        'student_id' => $me->id,
        'invoice_id' => $otherYearInvoice->id,
        'amount_paid' => 500,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => '2026-01-10',
    ]);

    $this->actingAs($me->user);

    $page = Livewire::test(AcademicHistoryClass::class, ['history' => $history->id])->instance();
    $summary = $page->getFeeSummary();

    expect($summary['totalPaid'])->toBe(1000.0)
        ->and($summary['url'])->toBe(AcademicHistoryFees::getUrl(['year' => 2025], panel: 'student'));
});

it("shows the class-year's main exam rank, gpa, and grade level, linking to the existing exam result view", function () {
    $class = Classes::create(['name' => 'Class Five', 'order' => 5]);
    $me = createAcademicHistoryTestStudent();

    $history = StudentClassHistory::create([
        'student_id' => $me->id,
        'class_id' => $class->id,
        'roll_no' => 1,
        'session_year' => 2025,
        'status' => PromotionStatus::Promoted,
    ]);

    $examType = ExamType::create(['name' => 'Final Exam', 'is_active' => true]);
    ExamTypeConfig::create(['exam_type_id' => $examType->id, 'type' => ExamConfigType::Main]);
    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => 2025,
        'start_date' => '2025-01-01',
        'end_date' => '2025-01-10',
        'is_published' => true,
    ]);
    StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $me->id,
        'class_id' => $class->id,
        'total_marks' => 95,
        'gpa' => 5.0,
        'class_rank' => 1,
    ]);

    $this->actingAs($me->user);

    $page = Livewire::test(AcademicHistoryClass::class, ['history' => $history->id])->instance();
    $summary = $page->getExamResultSummary();

    expect($summary)->not->toBeNull()
        ->and($summary['classRank'])->toBe(1)
        ->and($summary['gpa'])->toBe('5.00')
        ->and($summary['gradeLabel'])->toBe('A+')
        ->and($summary['url'])->toBe(ExamResultResource::getUrl('view', ['record' => $exam->id], panel: 'student'));
});

it('returns no exam result summary when the class-year has no main exam ranking', function () {
    $class = Classes::create(['name' => 'Class Five', 'order' => 5]);
    $me = createAcademicHistoryTestStudent();

    $history = StudentClassHistory::create([
        'student_id' => $me->id,
        'class_id' => $class->id,
        'roll_no' => 1,
        'session_year' => 2025,
        'status' => PromotionStatus::Promoted,
    ]);

    $this->actingAs($me->user);

    $page = Livewire::test(AcademicHistoryClass::class, ['history' => $history->id])->instance();

    expect($page->getExamResultSummary())->toBeNull();
});

it('filters the academic-history fee page to only the given year', function () {
    $me = createAcademicHistoryTestStudent();
    $feeType = FeeType::create(['name' => 'Tuition', 'is_active' => true]);

    $matching = StudentFeeInvoice::create([
        'student_id' => $me->id,
        'fee_type_id' => $feeType->id,
        'month' => 1,
        'year' => 2025,
        'original_amount' => 1000,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => 1000,
        'status' => 'unpaid',
    ]);
    $nonMatching = StudentFeeInvoice::create([
        'student_id' => $me->id,
        'fee_type_id' => $feeType->id,
        'month' => 1,
        'year' => 2026,
        'original_amount' => 800,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => 800,
        'status' => 'unpaid',
    ]);

    $this->actingAs($me->user);

    Livewire::test(AcademicHistoryFees::class, ['year' => 2025])
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$nonMatching]);
});

it('renders the academic history landing and class-detail pages over http', function () {
    $class = Classes::create(['name' => 'Class Five', 'order' => 5]);
    $me = createAcademicHistoryTestStudent();

    $history = StudentClassHistory::create([
        'student_id' => $me->id,
        'class_id' => $class->id,
        'roll_no' => 1,
        'session_year' => 2025,
        'status' => PromotionStatus::Promoted,
    ]);

    $this->actingAs($me->user);

    $this->get(AcademicHistory::getUrl(panel: 'student'))
        ->assertOk()
        ->assertSee('Class Five');

    $this->get(AcademicHistoryClass::getUrl(['history' => $history->id], panel: 'student'))
        ->assertOk()
        ->assertSee('Attendance')
        ->assertSee('School Fee')
        ->assertSee('Exam Result');
});
