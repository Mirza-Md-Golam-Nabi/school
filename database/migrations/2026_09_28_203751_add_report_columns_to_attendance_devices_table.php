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
            $table->json('reported_sizes')->nullable()->after('last_synced_at'); // ইউজার/ছাপ/কার্ড/রেকর্ডের ব্যবহার ও সীমা
            $table->timestamp('sizes_reported_at')->nullable()->after('reported_sizes');
            $table->json('unknown_device_users')->nullable()->after('sizes_reported_at'); // ডিভাইসে আছে কিন্তু সফটওয়্যারে চেনা নেই
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->dropColumn(['reported_sizes', 'sizes_reported_at', 'unknown_device_users']);
        });
    }
};
