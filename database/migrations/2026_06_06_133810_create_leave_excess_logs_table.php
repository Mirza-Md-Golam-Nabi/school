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
        Schema::create('leave_excess_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_application_id')->constrained('leave_applications')->cascadeOnDelete();
            $table->morphs('applicant'); // teacher_profiles, staff_profiles
            $table->unsignedInteger('allowed_days');
            $table->unsignedInteger('taken_days');
            $table->unsignedInteger('excess_days'); // taken_days - allowed_days
            $table->boolean('consequence_applied')->default(false); // future extension
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_excess_logs');
    }
};
