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
        Schema::table('exam_subject_configs', function (Blueprint $table) {
            $table->boolean('contributes_to_target')->default(true)->after('check_practical_pass');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_subject_configs', function (Blueprint $table) {
            $table->dropColumn('contributes_to_target');
        });
    }
};
