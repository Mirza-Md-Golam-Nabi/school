<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // খাতা-কলমে নেওয়া উপস্থিতিসহ একটা পরীক্ষা পর্যন্ত ছাত্রের মোট উপস্থিতি — class teacher বা admin সরাসরি লেখেন।
        // থাকলে মার্কশিট ও attendance ranking ডিভাইসের হিসাবের বদলে এটাই দেখায়।
        Schema::create('student_attendance_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            // মোট কার্যদিবস
            $table->unsignedSmallInteger('working_days');
            // মোট উপস্থিত দিন
            $table->unsignedSmallInteger('present_days');
            // কে লিখেছেন (class teacher / admin)
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['exam_id', 'student_id']);
            $table->index(['class_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_attendance_summaries');
    }
};
