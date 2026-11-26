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
     */
    public function up(): void
    {
        Schema::table('marksheets', function (Blueprint $table) {
            $table->string('verification_code', 16)->nullable()->unique()->after('exam_id'); // মার্কশিটের যাচাই কোড — QR-এ ও হাতে টাইপ করে রেজাল্ট মিলিয়ে দেখার জন্য
        });

        Marksheet::query()
            ->whereNull('verification_code')
            ->select('id')
            ->chunkById(500, function (Collection $marksheets): void {
                foreach ($marksheets as $marksheet) {
                    $marksheet->forceFill(['verification_code' => Marksheet::newVerificationCode()])->saveQuietly();
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
};
