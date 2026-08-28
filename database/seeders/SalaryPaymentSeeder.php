<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Enums\UserType;
use App\Models\AccountTransaction;
use App\Models\SalaryInvoice;
use App\Models\SalaryPayment;
use App\Models\SchoolAccount;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SalaryPaymentSeeder extends Seeder
{
    private const PAID_RATIO = 0.7;

    /**
     * Run the database seeds.
     *
     * Pays 70% of every still-unpaid salary invoice from "Main Account",
     * leaving the remaining 30% due — same account SalaryStructureSeeder
     * assigns as everyone's default. Must run after SalaryInvoiceSeeder.
     *
     * Bulk-inserts the payments and ledger entries directly (bypassing
     * ProcessIndividualSalaryPaymentAction, which is written for one admin-entered
     * payment at a time and re-queries admin users to notify on every call) and
     * posts one aggregate balance update, matching FeePaymentSeeder's approach.
     */
    public function run(): void
    {
        $account = SchoolAccount::where('name', 'Main Account')->first();

        if (! $account) {
            return;
        }

        $paidBy = User::where('user_type', UserType::Admin)->value('id')
            ?? User::query()->value('id');

        $invoices = SalaryInvoice::where('status', InvoiceStatus::Unpaid)->get(['id', 'invoice_no', 'net_amount']);

        if ($invoices->isEmpty()) {
            return;
        }

        $now = now();
        $paymentMethods = PaymentMethod::cases();
        $paymentRows = [];
        $invoiceIds = [];

        foreach ($invoices as $invoice) {
            $payAmount = round((float) $invoice->net_amount * self::PAID_RATIO, 2);

            if ($payAmount <= 0) {
                continue;
            }

            $paymentRows[] = [
                'salary_invoice_id' => $invoice->id,
                'amount_paid' => $payAmount,
                'payment_method' => fake()->randomElement($paymentMethods)->value,
                'transaction_id' => null,
                'payment_date' => $now->toDateString(),
                'school_account_id' => $account->id,
                'bulk_payment_id' => null,
                'paid_by' => $paidBy,
                'remarks' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $invoiceIds[] = $invoice->id;
        }

        if (empty($paymentRows)) {
            return;
        }

        $invoiceNoById = $invoices->pluck('invoice_no', 'id');

        DB::transaction(function () use ($paymentRows, $invoiceIds, $invoiceNoById, $account, $paidBy, $now) {
            collect($paymentRows)->chunk(500)->each(
                fn ($chunk) => SalaryPayment::insert($chunk->all())
            );

            // Every touched invoice was Unpaid (zero prior payments), so any
            // payment now on these invoice IDs is exactly what was just inserted.
            $insertedPayments = SalaryPayment::whereIn('salary_invoice_id', $invoiceIds)->get(['id', 'salary_invoice_id', 'amount_paid']);

            $transactionRows = $insertedPayments->map(fn (SalaryPayment $payment): array => [
                'account_id' => $account->id,
                'transaction_type' => TransactionType::Expense->value,
                'source_type' => TransactionSource::Salary->value,
                'source_id' => $payment->id,
                'amount' => $payment->amount_paid,
                'description' => "Salary payment - invoice #{$invoiceNoById->get($payment->salary_invoice_id)}",
                'transaction_date' => $now->toDateString(),
                'created_by' => $paidBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $transactionRows->chunk(500)->each(
                fn ($chunk) => AccountTransaction::insert($chunk->all())
            );

            SchoolAccount::whereKey($account->id)->decrement('current_balance', $insertedPayments->sum('amount_paid'));

            SalaryInvoice::whereIn('id', $invoiceIds)->update(['status' => InvoiceStatus::Partial->value]);
        });
    }
}
