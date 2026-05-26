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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_contribute_rules');
    }
};
