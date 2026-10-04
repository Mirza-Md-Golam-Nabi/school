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
        Schema::create('device_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_device_id')->constrained()->cascadeOnDelete();
            $table->string('enroll_id', 50); // ডিভাইসে যে ID দিয়ে আঙুল রেজিস্টার করা হয়েছে
            $table->morphs('enrollable'); // StudentProfile / TeacherProfile / StaffProfile
            $table->timestamps();

            $table->unique(['attendance_device_id', 'enroll_id']);
            $table->unique(['attendance_device_id', 'enrollable_type', 'enrollable_id'], 'device_users_person_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_users');
    }
};
