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
            // ডিভাইসের Device Capacity মেনু থেকে অ্যাডমিন নিজে বসান — কোডে hardcode নেই
            $table->unsignedInteger('user_capacity')->nullable()->after('is_active');
            $table->unsignedInteger('fingerprint_capacity')->nullable()->after('user_capacity');
            $table->unsignedInteger('card_capacity')->nullable()->after('fingerprint_capacity');
            $table->unsignedInteger('record_capacity')->nullable()->after('card_capacity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->dropColumn(['user_capacity', 'fingerprint_capacity', 'card_capacity', 'record_capacity']);
        });
    }
};
