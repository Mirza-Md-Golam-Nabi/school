<?php

use App\Models\Marksheet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Safe to re-run: a previous failed attempt may have already added the
     * column (MySQL DDL is not rolled back), so the column and index are only
     * added when missing.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('marksheets', 'verification_code')) {
            Schema::table('marksheets', function (Blueprint $table) {
                $table->string('verification_code', 16)->nullable()->after('exam_id'); // মার্কশিটের যাচাই কোড — QR-এ ও হাতে টাইপ করে রেজাল্ট মিলিয়ে দেখার জন্য
            });
        }

        if (! Schema::hasIndex('marksheets', ['verification_code'], 'unique')) {
            Schema::table('marksheets', function (Blueprint $table) {
                $table->unique('verification_code');
            });
        }

        Marksheet::query()
            ->whereNull('verification_code')
            ->select('id')
            ->chunkById(500, function (Collection $marksheets): void {
                foreach ($marksheets as $marksheet) {
                    $marksheet->forceFill(['verification_code' => $this->newVerificationCode()])->saveQuietly();
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marksheets', function (Blueprint $table) {
            $table->dropUnique(['verification_code']);
            $table->dropColumn('verification_code');
        });
    }

    /**
     * A unique code among marksheets only.
     *
     * Marksheet::newVerificationCode() also checks fee_payments.verification_code,
     * which a later migration adds, so it cannot be used here.
     */
    private function newVerificationCode(): string
    {
        do {
            $code = collect(range(1, Marksheet::VERIFICATION_CODE_LENGTH))
                ->map(fn (): string => Marksheet::VERIFICATION_CODE_ALPHABET[random_int(0, strlen(Marksheet::VERIFICATION_CODE_ALPHABET) - 1)])
                ->implode('');
        } while (Marksheet::query()->where('verification_code', $code)->exists());

        return $code;
    }
};
