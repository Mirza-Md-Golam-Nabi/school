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
            $table->float('contributed_marks')->nullable()->after('total_marks');
            $table->unsignedTinyInteger('contribution_percent')->nullable()->after('contributed_marks');
            $table->foreignId('contribution_source_exam_type_id')->nullable()->after('contribution_percent')->constrained('exam_types')->nullOnDelete();
            $table->json('contribution_source_breakdown')->nullable()->after('contribution_source_exam_type_id');
            $table->float('final_marks')->nullable()->after('contribution_source_breakdown');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_results', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contribution_source_exam_type_id');
            $table->dropColumn(['contributed_marks', 'contribution_percent', 'contribution_source_breakdown', 'final_marks']);
        });
    }
};
