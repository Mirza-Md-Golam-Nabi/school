<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * কলেজে একটা বিষয়ের একাধিক পত্র থাকে (যেমন: Biology → Biology 1st Paper,
     * Biology 2nd Paper)। পত্রের parent_subject_id-তে মূল বিষয় থাকে; মূল বিষয়
     * বা পত্রহীন সাধারণ বিষয়ের ক্ষেত্রে এটা ফাঁকা।
     */
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('parent_subject_id')
                ->nullable()
                ->after('code')
                ->constrained('subjects')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_subject_id');
        });
    }
};
