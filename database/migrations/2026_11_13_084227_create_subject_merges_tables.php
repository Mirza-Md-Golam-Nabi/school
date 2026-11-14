<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * দুইটা সাবজেক্ট মিলে রেজাল্টে একটা বিষয় (যেমন: বাংলা ১ম + ২য় পত্র = বাংলা)।
     * subject_merges = ক্লাসের ডিফল্ট; exam_subject_merges = পরীক্ষার নিজস্ব কপি
     * (পরীক্ষা তৈরির সময় ক্লাস থেকে কপি হয়, পরীক্ষায় বদলানো যায়)।
     */
    public function up(): void
    {
        Schema::create('subject_merges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            // মার্জ করা বিষয়ের নাম (যেমন: Bangla / বাংলা)
            $table->string('name');
            $table->string('name_bn')->nullable();
            // ১ম পত্র ও ২য় পত্র
            $table->foreignId('first_subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('second_subject_id')->constrained('subjects')->cascadeOnDelete();
            // ফাঁকা = School Settings-এর ডিফল্ট নিয়ম
            // combined = যৌথ নম্বরে পাশ, each_paper = প্রতিটা পত্রে আলাদা পাশ
            $table->string('pass_rule')->nullable();
            // combined = দুই পত্রের MCQ/লিখিত যোগ করে পাশ, each_paper = প্রতিটা পত্রে আলাদা
            $table->string('component_pass_rule')->nullable();
            // fail = কোনো পত্রে অনুপস্থিত হলে ফেল, count_as_zero = অনুপস্থিত পত্রে ০ ধরে হিসাব
            $table->string('absent_rule')->nullable();
            // decimal = দশমিক, nearest = কাছের পূর্ণসংখ্যা, up = ওপরে, down = নিচে
            $table->string('rounding')->nullable();
            $table->timestamps();

            // একটা সাবজেক্ট একটা ক্লাসে একটাই মার্জে থাকতে পারে
            $table->unique(['class_id', 'first_subject_id']);
            $table->unique(['class_id', 'second_subject_id']);
        });

        Schema::create('exam_subject_merges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->foreignId('first_subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('second_subject_id')->constrained('subjects')->cascadeOnDelete();
            // কপির সময় ডিফল্ট বসিয়ে স্থির করা নিয়ম — পরে School Settings বদলালেও এই পরীক্ষা বদলায় না
            $table->string('pass_rule');
            $table->string('component_pass_rule');
            $table->string('absent_rule');
            $table->string('rounding');
            $table->timestamps();

            $table->unique(['exam_id', 'first_subject_id']);
            $table->unique(['exam_id', 'second_subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_subject_merges');
        Schema::dropIfExists('subject_merges');
    }
};
