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
        Schema::create('student_merit_rankings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->decimal('total_marks', 8, 2)->default(0);
            $table->decimal('gpa', 3, 2)->default(0);
            $table->unsignedSmallInteger('class_rank')->nullable();
            $table->unsignedSmallInteger('section_rank')->nullable();
            $table->timestamps();

            $table->unique(['exam_id', 'student_id'], 'unique_exam_student_ranking');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_merit_rankings');
    }
};
