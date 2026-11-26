<?php

use App\Models\FeePayment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fee_payments', function (Blueprint $table) {
            $table->string('verification_code', 16)->nullable()->index()->after('payment_batch_id'); // পেমেন্ট স্লিপের যাচাই কোড — একই স্লিপের (batch) সব পেমেন্টে একই কোড
        });

        // toBase(): updated_at না বদলাতে — নইলে যাচাই পেজে পুরনো রসিদও "সংশোধিত" দেখাবে
        FeePayment::query()
            ->whereNull('verification_code')
            ->select(['id', 'payment_batch_id'])
            ->chunkById(500, function (Collection $payments): void {
                foreach ($payments as $payment) {
                    $query = filled($payment->payment_batch_id)
                        ? FeePayment::query()->where('payment_batch_id', $payment->payment_batch_id)
                        : FeePayment::query()->whereKey($payment->id);

                    $query->whereNull('verification_code')
                        ->toBase()
                        ->update(['verification_code' => FeePayment::newVerificationCode()]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_payments', function (Blueprint $table) {
            $table->dropIndex(['verification_code']);
            $table->dropColumn('verification_code');
        });
    }
};
