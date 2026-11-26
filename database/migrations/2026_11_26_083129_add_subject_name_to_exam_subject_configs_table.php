<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * শুধু এই পরীক্ষার কাগজপত্রে (marksheet, tabulation sheet, admit card,
     * routine ইত্যাদি) subject-এর যে নাম ছাপা হবে। ফাঁকা থাকলে subject-এর
     * আসল নাম; subject-এর নাম বা অন্য পরীক্ষা বদলায় না।
     */
    public function up(): void
    {
        Schema::table('exam_subject_configs', function (Blueprint $table) {
            $table->string('subject_name')->nullable()->after('subject_id');
            $table->string('subject_name_bn')->nullable()->after('subject_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_subject_configs', function (Blueprint $table) {
            $table->dropColumn(['subject_name', 'subject_name_bn']);
        });
    }
};
