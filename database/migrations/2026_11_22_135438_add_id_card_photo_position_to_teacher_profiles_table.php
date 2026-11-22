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
        Schema::table('teacher_profiles', function (Blueprint $table) {
            // ID কার্ডের গোল ফ্রেমে শিক্ষকের ছবি কোথায় ও কতটা zoom করে বসবে: {x, y, zoom}
            // null = ডিফল্ট পজিশন (মাঝখান থেকে, উপরের দিকে একটু বেশি)
            $table->json('id_card_photo_position')->nullable()->after('signature');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->dropColumn('id_card_photo_position');
        });
    }
};
