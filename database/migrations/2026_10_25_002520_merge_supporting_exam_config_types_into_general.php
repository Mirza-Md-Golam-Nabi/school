<?php

use App\Models\ExamTypeConfig;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * পরীক্ষার ধরন এখন দুটো: main = রেজাল্ট Academic History ও Promotion-এ আসে,
     * general = নিজের রেজাল্ট হয় কিন্তু সেখানে আসে না। কোন পরীক্ষা কোথায় যোগ
     * হবে সেটা এখন রেজাল্টের নিয়ম ঠিক করে, তাই supporting/not_supporting একসাথে general।
     */
    public function up(): void
    {
        ExamTypeConfig::withTrashed()
            ->whereIn('type', ['supporting', 'not_supporting'])
            ->update(['type' => 'general']);
    }

    public function down(): void
    {
        ExamTypeConfig::withTrashed()
            ->where('type', 'general')
            ->update(['type' => 'not_supporting']);
    }
};
