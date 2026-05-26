<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_subject_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->unsignedSmallInteger('mcq_total')->nullable();
            $table->unsignedSmallInteger('mcq_pass_mark')->nullable();
            $table->unsignedSmallInteger('written_total')->nullable();
            $table->unsignedSmallInteger('written_pass_mark')->nullable();
            $table->unsignedSmallInteger('practical_total')->nullable();
            $table->unsignedSmallInteger('practical_pass_mark')->nullable();
            $table->unsignedSmallInteger('total_marks');
            $table->unsignedSmallInteger('pass_mark');
            $table->boolean('check_mcq_pass')->default(false);
            $table->boolean('check_written_pass')->default(false);
            $table->boolean('check_practical_pass')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['exam_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_subject_configs');
    }
};
