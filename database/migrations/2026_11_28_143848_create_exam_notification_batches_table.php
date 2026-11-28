<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_notification_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            // subject_marks = বিষয়ভিত্তিক মার্ক, result = সম্পূর্ণ রেজাল্ট
            $table->string('kind');
            // বিষয়ের মার্ক পাঠালে কোন কোন subject (result-এর ক্ষেত্রে null)
            $table->json('subject_ids')->nullable();
            // যে চ্যানেলগুলোতে পাঠানো হয়েছে: push, sms
            $table->json('channels');
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('push_count')->default(0);
            $table->unsignedInteger('sms_count')->default(0);
            // SmsBatchResult-এর status: queued, insufficient_balance, disabled ইত্যাদি (SMS না চাইলে null)
            $table->string('sms_status')->nullable();
            $table->decimal('sms_cost', 12, 4)->default(0);
            $table->timestamps();

            $table->index(['exam_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_notification_batches');
    }
};
