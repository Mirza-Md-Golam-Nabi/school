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
            // এই সময়ের পরে শিক্ষক ঢুকলে Late Present (LP); খালি থাকলে শিক্ষার্থীর নিয়ম (Entry Time + Late Threshold) প্রযোজ্য
            $table->time('teacher_late_after')->nullable()->after('exit_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn('teacher_late_after');
        });
    }
};
