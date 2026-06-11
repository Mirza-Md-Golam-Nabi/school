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
        Schema::create('leave_attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_application_id')->constrained('leave_applications')->cascadeOnDelete();
            $table->date('date');
            $table->string('status'); // on_leave, joined, absent
            $table->boolean('is_within_approved_range')->default(true); // approved range এর মধ্যে জয়েন করেছে কিনা
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['leave_application_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_attendance_logs');
    }
};
