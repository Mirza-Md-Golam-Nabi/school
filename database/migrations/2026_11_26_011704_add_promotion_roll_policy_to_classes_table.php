<?php

use App\Enums\PromotionRollPolicy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * এই ক্লাস থেকে promote (বা repeat) করার সময় নতুন রোল কীভাবে বসবে:
     * merit = Main পরীক্ষার মেধাক্রম অনুযায়ী, keep_current = আগের রোলই থাকবে
     * (যেমন: কলেজে ১ম বর্ষের রোল দিয়েই ২য় বর্ষ শেষ হয়)।
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->string('promotion_roll_policy', 20)
                ->default(PromotionRollPolicy::Merit->value)
                ->after('has_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('promotion_roll_policy');
        });
    }
};
