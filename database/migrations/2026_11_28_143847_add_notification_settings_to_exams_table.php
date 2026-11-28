<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            // বিষয়ভিত্তিক মার্ক কোন চ্যানেলে যাবে: push, sms (null = ডিফল্ট, শুধু push)
            $table->json('subject_marks_channels')->nullable()->after('is_published');
            // সম্পূর্ণ রেজাল্ট কোন চ্যানেলে যাবে: push, sms (null = ডিফল্ট, push + sms)
            $table->json('result_channels')->nullable()->after('subject_marks_channels');
            // Publish করার সময় রেজাল্ট নোটিফিকেশন নিজে থেকে যাবে কি না
            $table->boolean('notify_result_on_publish')->default(true)->after('result_channels');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['subject_marks_channels', 'result_channels', 'notify_result_on_publish']);
        });
    }
};
