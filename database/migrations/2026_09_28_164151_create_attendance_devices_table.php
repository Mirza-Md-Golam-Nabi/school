<?php

use App\Enums\AttendanceDeviceDriver;
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
        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('serial_number')->nullable()->unique(); // ডিভাইসের সিরিয়াল নম্বর
            $table->string('driver')->default(AttendanceDeviceDriver::ZkPull->value); // zk_pull = ল্যাপটপ সিঙ্ক, adms = ডিভাইস নিজে push করে
            $table->string('api_token_hash', 64)->unique(); // token-এর sha256 হ্যাশ, আসল token সংরক্ষিত থাকে না
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_devices');
    }
};
