<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * এক উৎস → এক target-এর পুরনো নিয়ম; এখন রেজাল্টের নিয়ম (`result_rules`) এর জায়গা নিয়েছে।
     */
    public function up(): void
    {
        Schema::dropIfExists('exam_contribute_rules');
    }

    public function down(): void
    {
        Schema::create('exam_contribute_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_exam_type_id')->constrained('exam_types')->cascadeOnDelete();
            $table->foreignId('target_exam_type_id')->constrained('exam_types')->cascadeOnDelete();
            $table->unsignedTinyInteger('contribution_percent');
            $table->unsignedSmallInteger('session_year');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
