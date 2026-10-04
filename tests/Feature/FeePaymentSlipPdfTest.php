<?php

use App\Actions\ProcessFeePaymentAction;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

function createSlipTestStudent(): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 1,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function createSlipTestInvoice(StudentProfile $student, float $netAmount, ?int $month = null): StudentFeeInvoice
{
    $feeType = FeeType::create(['name' => 'Fee '.str()->random(4), 'is_monthly' => $month !== null, 'is_active' => true]);

    return StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'month' => $month,
        'year' => now()->year,
        'original_amount' => $netAmount,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => $netAmount,
        'status' => InvoiceStatus::Unpaid,
    ]);
}

it('assigns the same payment_batch_id to every payment created from one multi-invoice submission', function () {
    $student = createSlipTestStudent();
    $invoiceOne = createSlipTestInvoice($student, 300, month: 1);
    $invoiceTwo = createSlipTestInvoice($student, 200, month: 2);

    $firstPayment = app(ProcessFeePaymentAction::class)->handle([
        'invoice_ids' => [$invoiceOne->id, $invoiceTwo->id],
        'amount_paid' => 500,
        'receipt_no' => 'RCP-BATCH1',
        'student_id' => $student->id,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
    ]);

    $batchPayments = FeePayment::where('payment_batch_id', $firstPayment->payment_batch_id)->get();

    expect($firstPayment->payment_batch_id)->not->toBeNull()
        ->and($batchPayments)->toHaveCount(2)
        ->and($batchPayments->pluck('invoice_id')->sort()->values()->all())
        ->toEqual([$invoiceOne->id, $invoiceTwo->id]);
});

it('downloads a combined payment slip pdf listing every invoice in the batch', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $student = createSlipTestStudent();
    $invoiceOne = createSlipTestInvoice($student, 300, month: 1);
    $invoiceTwo = createSlipTestInvoice($student, 200, month: 2);

    $firstPayment = app(ProcessFeePaymentAction::class)->handle([
        'invoice_ids' => [$invoiceOne->id, $invoiceTwo->id],
        'amount_paid' => 500,
        'receipt_no' => 'RCP-BATCH2',
        'student_id' => $student->id,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($admin)->get(route('fee-payments.slip.download', $firstPayment->payment_batch_id));

    $response->assertStatus(Response::HTTP_OK);
    $response->assertHeader('Content-Type', 'application/pdf');

    $pdf = $response->getContent();
    expect($pdf)->toStartWith('%PDF');

    // A5 portrait media box is ~419.5 x 595.3 pt.
    preg_match('/\/MediaBox\s*\[0 0 ([\d.]+) ([\d.]+)\]/', $pdf, $mediaBox);
    expect((float) $mediaBox[1])->toBeGreaterThan(400.0)->toBeLessThan(440.0);
});

it('spells out the total paid amount in words on the slip', function () {
    $student = createSlipTestStudent();
    $invoiceOne = createSlipTestInvoice($student, 300, month: 1);
    $invoiceTwo = createSlipTestInvoice($student, 200, month: 2);

    $firstPayment = app(ProcessFeePaymentAction::class)->handle([
        'invoice_ids' => [$invoiceOne->id, $invoiceTwo->id],
        'amount_paid' => 500,
        'receipt_no' => 'RCP-BATCH3',
        'student_id' => $student->id,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
    ]);

    $payments = FeePayment::where('payment_batch_id', $firstPayment->payment_batch_id)->get();

    $html = view('documents.fee-payment-slip', ['payments' => $payments])->render();

    expect($html)->toContain('In Words:')
        ->toContain('Five Hundred Taka Only');
});

it('aborts with 404 for an unknown payment batch id', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $response = $this->actingAs($admin)->get(route('fee-payments.slip.download', 'non-existent-batch'));

    $response->assertStatus(Response::HTTP_NOT_FOUND);
});

it("prints the receiver's name above the Received By signature line", function () {
    $receiver = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true, 'name' => 'Slip Receiver Karim']);
    $student = createSlipTestStudent();
    $invoice = createSlipTestInvoice($student, 300, month: 1);

    $firstPayment = app(ProcessFeePaymentAction::class)->handle([
        'invoice_ids' => [$invoice->id],
        'amount_paid' => 300,
        'receipt_no' => 'RCP-SIGNER',
        'student_id' => $student->id,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
        'received_by' => $receiver->id,
    ]);

    $payments = FeePayment::with(['student.user', 'student.class', 'student.section', 'student.group', 'invoice.feeType', 'receivedBy'])
        ->where('payment_batch_id', $firstPayment->payment_batch_id)
        ->get();

    $html = view('documents.fee-payment-slip', ['payments' => $payments])->render();

    expect($html)->toContain('<div class="signer-name">Slip Receiver Karim</div>');
});
