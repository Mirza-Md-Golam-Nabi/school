<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * এক উৎসের contribution কলামগুলোর বদলে রেজাল্টের নিয়মের সব অংশের হিসাব
     * (`result_breakdown`) আর চূড়ান্ত মার্কের পূর্ণমান (`final_full_marks`)।
     */
    public function up(): void
    {
        Schema::table('student_results', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contribution_source_exam_type_id');
            $table->dropColumn(['contributed_marks', 'contribution_percent', 'contribution_source_breakdown']);
        });

        Schema::table('student_results', function (Blueprint $table) {
            $table->json('result_breakdown')->nullable()->after('total_marks');
            $table->float('final_full_marks')->nullable()->after('final_marks');
        });
    }

    public function down(): void
    {
        Schema::table('student_results', function (Blueprint $table) {
            $table->dropColumn(['result_breakdown', 'final_full_marks']);
        });

        Schema::table('student_results', function (Blueprint $table) {
            $table->float('contributed_marks')->nullable()->after('total_marks');
            $table->unsignedTinyInteger('contribution_percent')->nullable()->after('contributed_marks');
            $table->foreignId('contribution_source_exam_type_id')->nullable()->after('contribution_percent')->constrained('exam_types')->nullOnDelete();
            $table->json('contribution_source_breakdown')->nullable()->after('contribution_source_exam_type_id');
        });
    }
};
