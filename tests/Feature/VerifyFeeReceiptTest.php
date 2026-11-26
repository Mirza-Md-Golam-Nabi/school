<?php

use App\Actions\BuildFeePaymentSlipPdfAction;
use App\Actions\ProcessFeePaymentAction;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\Marksheet;
use App\Models\SchoolSetting;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * One slip paying two monthly invoices (300 + 200) for "Karim Hossain".
 *
 * @return Collection<int, FeePayment>
 */
function makeVerifiableFeeSlip(string $receiptNo = 'RCP-QR1'): Collection
{
    $user = User::factory()->create(['name' => 'Karim Hossain', 'user_type' => UserType::Student, 'is_active' => true]);
    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 7,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $invoiceIds = collect([[1, 300], [2, 200]])->map(fn (array $line): int => StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'month' => $line[0],
        'year' => now()->year,
        'original_amount' => $line[1],
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => $line[1],
        'status' => InvoiceStatus::Unpaid,
    ])->id);

    $firstPayment = app(ProcessFeePaymentAction::class)->handle([
        'invoice_ids' => $invoiceIds->all(),
        'amount_paid' => 500,
        'receipt_no' => $receiptNo,
        'student_id' => $student->id,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
    ]);

    return FeePayment::with(['student.user', 'student.class', 'student.section', 'student.group', 'invoice.feeType', 'receivedBy'])
        ->where('payment_batch_id', $firstPayment->payment_batch_id)
        ->orderBy('id')
        ->get();
}

it('gives every payment on one slip the same short verification code', function () {
    $payments = makeVerifiableFeeSlip();

    expect($payments)->toHaveCount(2)
        ->and($payments->pluck('verification_code')->unique())->toHaveCount(1)
        ->and($payments->first()->verification_code)->toMatch('/^['.Marksheet::VERIFICATION_CODE_ALPHABET.']{8}$/');
});

it('gives separate slips separate codes', function () {
    $first = makeVerifiableFeeSlip()->first();
    $second = makeVerifiableFeeSlip('RCP-QR2')->first();

    expect($first->verification_code)->not->toBe($second->verification_code);
});

it('shows the slip lines and total on the public verification page without login', function () {
    $payment = makeVerifiableFeeSlip()->first();

    $this->get($payment->verificationUrl())
        ->assertOk()
        ->assertSee('Karim Hossain')
        ->assertSee('RCP-QR1')
        ->assertSee('Tuition Fee')
        ->assertSee('৳ ৫০০.০০')
        ->assertSee($payment->formattedVerificationCode())
        ->assertSee('noindex', false)
        ->assertDontSee('ইস্যুর পর এই রসিদের তথ্য সংশোধন করা হয়েছে');
});

it('shows the current amount and flags the slip as revised when a payment is edited later', function () {
    $payments = makeVerifiableFeeSlip();

    $this->travel(2)->hours();
    $payments->last()->update(['amount_paid' => 150]);

    $this->get($payments->first()->verificationUrl())
        ->assertOk()
        ->assertSee('৳ ৪৫০.০০')
        ->assertDontSee('৳ ৫০০.০০')
        ->assertSee('ইস্যুর পর এই রসিদের তথ্য সংশোধন করা হয়েছে');
});

it('answers not found once every payment on the slip is deleted', function () {
    $payments = makeVerifiableFeeSlip();
    $url = $payments->first()->verificationUrl();

    $payments->each->delete();

    $this->get($url)
        ->assertNotFound()
        ->assertSee('রসিদ পাওয়া যায়নি')
        ->assertDontSee('Karim Hossain');
});

it('opens the receipt from a code typed in by hand on the shared verify page', function () {
    $payment = makeVerifiableFeeSlip()->first();
    $typed = strtolower(' '.$payment->formattedVerificationCode().' ');

    $this->get(route('verify', ['code' => $typed]))
        ->assertRedirect(route('fee-receipts.verify', ['code' => $payment->verification_code]));
});

it('prints the verification QR code, short link and code on the payment slip', function () {
    $payments = makeVerifiableFeeSlip();

    $html = view('documents.fee-payment-slip', ['payments' => $payments])->render();

    expect($html)->toContain('<barcode')
        ->toContain('type="QR"')
        ->toContain($payments->first()->verificationUrl())
        ->toContain(preg_replace('#^https?://#', '', route('verify')))
        ->toContain($payments->first()->formattedVerificationCode());
});

it('leaves the QR code off the slip when the school turns it off', function () {
    SchoolSetting::set(FeePayment::SHOW_QR_SETTING, '0');
    $payments = makeVerifiableFeeSlip();

    $html = view('documents.fee-payment-slip', ['payments' => $payments])->render();

    expect($html)->not->toContain('<barcode');
});

it('renders the payment slip pdf with the QR code', function () {
    $pdf = app(BuildFeePaymentSlipPdfAction::class)->handle(makeVerifiableFeeSlip());

    expect($pdf)->toStartWith('%PDF');
});
