<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admit card-এর ধরন: simple = আগের সাধারণ কার্ড, with_routine = নিচে পরীক্ষার রুটিনসহ।
     */
    public function up(): void
    {
        Schema::table('admit_cards', function (Blueprint $table) {
            $table->string('design')->default('simple')->after('page_size');
        });
    }

    public function down(): void
    {
        Schema::table('admit_cards', function (Blueprint $table) {
            $table->dropColumn('design');
        });
    }
};
