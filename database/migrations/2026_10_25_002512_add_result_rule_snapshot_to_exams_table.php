<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * রেজাল্ট হিসাবের মুহূর্তে রেজাল্টের নিয়মের কপি — প্রকাশের পর নিয়ম বদলালেও
     * এই পরীক্ষার রেজাল্ট বদলায় না।
     */
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->json('result_rule_snapshot')->nullable()->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('result_rule_snapshot');
        });
    }
};
