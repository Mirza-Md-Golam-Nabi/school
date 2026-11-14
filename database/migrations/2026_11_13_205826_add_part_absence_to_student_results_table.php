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
        Schema::table('student_results', function (Blueprint $table) {
            // লিখিত পরীক্ষায় (MCQ + CQ) অনুপস্থিত
            $table->boolean('is_theory_absent')->default(false)->after('is_absent');
            // ব্যবহারিক পরীক্ষায় অনুপস্থিত
            $table->boolean('is_practical_absent')->default(false)->after('is_theory_absent');
        });

        // আগে যারা পুরো বিষয়ে অনুপস্থিত ছিল, তারা দুই অংশেই অনুপস্থিত।
        Schema::getConnection()->table('student_results')
            ->where('is_absent', true)
            ->update(['is_theory_absent' => true, 'is_practical_absent' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_results', function (Blueprint $table) {
            $table->dropColumn(['is_theory_absent', 'is_practical_absent']);
        });
    }
};
