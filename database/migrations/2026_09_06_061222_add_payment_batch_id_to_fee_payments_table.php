<?php

use App\Models\FeePayment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fee_payments', function (Blueprint $table) {
            $table->string('payment_batch_id')->nullable()->after('receipt_no')->index();
        });

        // Payments created from the same submission already share a receipt_no
        // prefix (RCP-XXXX, RCP-XXXX/2, RCP-XXXX/3, ...). Group those together
        // onto one batch id so historical multi-invoice payments can also print
        // a combined payment slip.
        FeePayment::query()
            ->orderBy('id')
            ->get(['id', 'receipt_no'])
            ->groupBy(fn (FeePayment $payment): string => preg_replace('#/\d+$#', '', $payment->receipt_no))
            ->each(function ($payments): void {
                $batchId = (string) Str::uuid();

                FeePayment::whereIn('id', $payments->pluck('id'))->update(['payment_batch_id' => $batchId]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_payments', function (Blueprint $table) {
            $table->dropColumn('payment_batch_id');
        });
    }
};
