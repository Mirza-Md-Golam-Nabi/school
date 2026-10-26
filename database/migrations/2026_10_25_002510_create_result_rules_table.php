<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * রেজাল্টের নিয়ম: কোন পরীক্ষার রেজাল্ট কোন কোন পরীক্ষার কত % মিলিয়ে হবে,
     * কত পূর্ণমানে, আর কোন কোন ক্লাসে খাটবে।
     */
    public function up(): void
    {
        Schema::create('result_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // যে পরীক্ষার রেজাল্ট এই নিয়মে তৈরি হবে (যেমন: ফাইনাল)
            $table->foreignId('target_exam_type_id')->constrained('exam_types')->cascadeOnDelete();
            // ফাঁকা = School Settings-এর ডিফল্ট পূর্ণমান
            $table->decimal('full_marks', 8, 2)->nullable();
            // decimal = দশমিকের দুই ঘর, nearest = কাছের পূর্ণসংখ্যা, up = সবসময় ওপরে
            $table->string('rounding')->default('decimal');
            $table->timestamps();
        });

        Schema::create('result_rule_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_type_id')->constrained('exam_types')->cascadeOnDelete();
            // রেজাল্টে এই অংশের ভাগ (%) — একটা নিয়মের সব অংশ মিলে ১০০
            $table->decimal('weight_percent', 5, 2);
            // এই অংশে আলাদাভাবে পাস করা লাগবে কি না
            $table->boolean('must_pass')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['result_rule_id', 'exam_type_id']);
        });

        Schema::create('result_rule_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_rule_id')->constrained()->cascadeOnDelete();
            // একটা ক্লাস একটাই নিয়মে থাকতে পারে
            $table->foreignId('class_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_rule_classes');
        Schema::dropIfExists('result_rule_components');
        Schema::dropIfExists('result_rules');
    }
};
