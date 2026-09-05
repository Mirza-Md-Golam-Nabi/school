<?php

use App\Enums\OptionalSubjectRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * প্রতিটা student, group-এর optional subject pool (class_group_subject-এ
     * subject_type = optional) থেকে নিজের main_optional ও extra_optional
     * subject বেছে নেয় — এই টেবিলে সেই choice-টা student + class ভিত্তিক
     * সংরক্ষণ করা হয়, যাতে ক্লাস পরিবর্তন (promotion) হলেও আগের বছরের
     * choice অক্ষত থাকে।
     */
    public function up(): void
    {
        Schema::create('student_optional_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('student_profiles')
                ->cascadeOnDelete();
            $table->foreignId('class_id')
                ->constrained('classes')
                ->cascadeOnDelete();
            $table->foreignId('group_id')
                ->constrained('groups')
                ->cascadeOnDelete();
            $table->foreignId('subject_id')
                ->constrained('subjects')
                ->cascadeOnDelete();
            $table->string('role', 20)
                ->default(OptionalSubjectRole::MainOptional);
            $table->timestamps();

            // প্রতি student প্রতি class-এ একটাই main_optional এবং একটাই extra_optional থাকতে পারবে
            $table->unique(['student_id', 'class_id', 'role'], 'unique_student_class_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_optional_subjects');
    }
};
