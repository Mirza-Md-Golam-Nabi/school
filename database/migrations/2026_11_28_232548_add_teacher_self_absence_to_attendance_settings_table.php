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
        Schema::table('attendance_settings', function (Blueprint $table) {
            // শিক্ষক নিজের প্যানেল থেকে Absent / Leave দিতে পারবেন কিনা; বন্ধ থাকলে শুধু Present দিতে পারবেন
            $table->boolean('teachers_may_self_mark_absence')->default(false)->after('teacher_late_after');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn('teachers_may_self_mark_absence');
        });
    }
};
