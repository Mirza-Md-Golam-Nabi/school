<?php

use App\Models\StudentProfile;
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
        Schema::table('student_profiles', function (Blueprint $table) {
            // ৮ ডিজিটের Student ID — প্রথম ৩ ডিজিট স্কুল কোড, পরের ৫ ডিজিট প্রোফাইলের id; এটা দিয়েই student login করে
            $table->string('student_id_no', 20)->nullable()->unique()->after('user_id');
        });

        // আগে থেকে থাকা সব student-কে ID দেওয়া হচ্ছে
        StudentProfile::withTrashed()
            ->whereNull('student_id_no')
            ->eachById(function (StudentProfile $profile): void {
                $profile->forceFill(['student_id_no' => StudentProfile::makeStudentIdNo($profile->id)])->saveQuietly();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropUnique(['student_id_no']);
            $table->dropColumn('student_id_no');
        });
    }
};
