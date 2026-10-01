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
            // টিক থাকলে (ডিফল্ট) Late Present (LP) উপস্থিত হিসেবে গোনা হয়; টিক তুলে দিলে গোনা হয় না
            $table->boolean('count_late_students')->default(true)->after('late_threshold_minutes');
            $table->boolean('count_late_teachers')->default(true)->after('count_late_students');
            $table->boolean('count_late_staff')->default(true)->after('count_late_teachers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn(['count_late_students', 'count_late_teachers', 'count_late_staff']);
        });
    }
};
