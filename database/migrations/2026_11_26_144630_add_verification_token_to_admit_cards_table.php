<?php

use App\Models\AdmitCard;
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
        Schema::table('admit_cards', function (Blueprint $table) {
            $table->string('verification_token', 32)->nullable()->unique()->after('exam_id'); // কার্ডের QR-এর যাচাই লিংকের গোপন টোকেন — অনুমান করা যায় না
        });

        AdmitCard::query()
            ->whereNull('verification_token')
            ->select('id')
            ->chunkById(500, function (Collection $admitCards): void {
                foreach ($admitCards as $admitCard) {
                    $admitCard->forceFill(['verification_token' => AdmitCard::newVerificationToken()])->saveQuietly();
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admit_cards', function (Blueprint $table) {
            $table->dropUnique(['verification_token']);
            $table->dropColumn('verification_token');
        });
    }
};
