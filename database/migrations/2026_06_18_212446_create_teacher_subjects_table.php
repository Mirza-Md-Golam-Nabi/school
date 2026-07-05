<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teacher_profiles')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->smallInteger('session_year')->unsigned();
            $table->timestamps();

            $table->unique(
                ['teacher_id', 'subject_id', 'class_id', 'section_id', 'session_year'],
                'teacher_subject_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_subjects');
    }
};
