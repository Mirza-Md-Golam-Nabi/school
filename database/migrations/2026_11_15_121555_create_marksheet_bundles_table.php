<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per exam + bundle type: a ready-made combined marksheet PDF,
     * built by a queue job whenever the exam's marksheets are (re)generated,
     * so downloading it never makes the admin wait.
     */
    public function up(): void
    {
        Schema::create('marksheet_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            // all = পাস ও ফেল সবার marksheet, passed = শুধু পাশ করা, failed = শুধু ফেল করা
            $table->enum('type', ['all', 'passed', 'failed']);
            // en = English, bn = বাংলা — যে ভাষায় PDF তৈরি হয়েছে
            $table->string('subject_language', 5)->default('en');
            $table->string('file_path')->nullable();
            $table->unsignedInteger('student_count')->default(0);
            $table->boolean('is_generated')->default(false);
            // প্রতিবার নতুন generate-এ নতুন token — পুরনো job যেন নতুন ফাইল overwrite না করে
            $table->uuid('generation_token')->nullable();
            $table->timestamp('file_generated_at')->nullable();
            $table->timestamps();

            $table->unique(['exam_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marksheet_bundles');
    }
};
