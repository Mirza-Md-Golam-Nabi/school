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
        Schema::table('attendance_devices', function (Blueprint $table) {
            // ডিভাইসের সবচেয়ে পুরনো record এত দিনের বেশি পুরনো হলে sync script পুরো attendance log মুছে দেয়; খালি = কখনো মোছে না
            $table->unsignedSmallInteger('log_retention_days')->nullable()->after('record_capacity');
            // sync script সর্বশেষ কখন ডিভাইসের attendance log মুছেছে
            $table->timestamp('log_cleared_at')->nullable()->after('sizes_reported_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->dropColumn(['log_retention_days', 'log_cleared_at']);
        });
    }
};
