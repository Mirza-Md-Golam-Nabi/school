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
        Schema::table('admit_cards', function (Blueprint $table) {
            $table->string('routine_language', 2)->default('en')->after('design'); // en = ইংরেজি, bn = বাংলা — পরীক্ষার রুটিনের ভাষা
            $table->string('student_info_language', 2)->default('en')->after('routine_language'); // en = ইংরেজি, bn = বাংলা — শিক্ষার্থীর তথ্যের ভাষা
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admit_cards', function (Blueprint $table) {
            $table->dropColumn(['routine_language', 'student_info_language']);
        });
    }
};
