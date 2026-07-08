<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserType;
use App\Models\FeePayment;
use App\Models\StudentFeeInvoice;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class FeePaymentSeeder extends Seeder
{
    private const PAID_RATIO = 0.7;

    /**
     * Run the database seeds.
     *
     * Per student, fully pays off the oldest ~70% of their invoices in
     * chronological order (Jan, Feb, Mar, ...), leaving the most recent
     * invoices unpaid — no random selection.
     */
    public function run(): void
    {
        $studentIds = StudentFeeInvoice::query()->distinct()->pluck('student_id');

        if ($studentIds->isEmpty()) {
            return;
        }

        $receivedBy = User::where('user_type', UserType::Admin)->value('id')
            ?? User::query()->value('id');

        $paymentMethods = PaymentMethod::cases();
        $now = now();
        $rows = [];
        $paidInvoiceIds = [];

        foreach ($studentIds as $studentId) {
            $invoices = StudentFeeInvoice::where('student_id', $studentId)
                ->orderBy('year')
                ->orderByRaw('COALESCE(month, 13)')
                ->orderBy('id')
                ->get();

            $targetPaidCount = (int) floor($invoices->count() * self::PAID_RATIO);
            $alreadyPaidCount = $invoices->where('status', InvoiceStatus::Paid)->count();
            $remainingToPay = max(0, $targetPaidCount - $alreadyPaidCount);

            if ($remainingToPay === 0) {
                continue;
            }

            $toPay = $invoices->where('status', InvoiceStatus::Unpaid)->take($remainingToPay);

            foreach ($toPay as $invoice) {
                $method = fake()->randomElement($paymentMethods);

                $rows[] = [
                    'receipt_no' => 'RCPT-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT),
                    'student_id' => $invoice->student_id,
                    'invoice_id' => $invoice->id,
                    'amount_paid' => $invoice->net_amount,
                    'payment_method' => $method->value,
                    'transaction_id' => $method === PaymentMethod::Cash ? null : strtoupper(fake()->bothify('TXN-########')),
                    'payment_date' => $this->paymentDate($invoice)->toDateString(),
                    'received_by' => $receivedBy,
                    'remarks' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $paidInvoiceIds[] = $invoice->id;
            }
        }

        if (empty($rows)) {
            return;
        }

        collect($rows)->chunk(500)->each(fn ($chunk) => FeePayment::insert($chunk->all()));

        StudentFeeInvoice::whereIn('id', $paidInvoiceIds)->update(['status' => InvoiceStatus::Paid->value]);
    }

    private function paymentDate(StudentFeeInvoice $invoice): Carbon
    {
        if ($invoice->month) {
            return Carbon::create($invoice->year, $invoice->month, fake()->numberBetween(1, 28));
        }

        $yearStart = Carbon::create($invoice->year, 1, 1);
        $yesterday = Carbon::yesterday();
        $end = $yearStart->year === $yesterday->year ? $yesterday : $yearStart->copy()->endOfYear();

        return Carbon::createFromTimestamp(fake()->numberBetween($yearStart->timestamp, max($yearStart->timestamp, $end->timestamp)));
    }
}
