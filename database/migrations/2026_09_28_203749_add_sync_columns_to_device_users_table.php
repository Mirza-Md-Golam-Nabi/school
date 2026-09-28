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
        Schema::table('device_users', function (Blueprint $table) {
            $table->string('card_number', 50)->nullable()->after('enroll_id'); // ডিভাইস থেকে রিপোর্ট করা RFID কার্ড নম্বর
            $table->unsignedTinyInteger('fingerprint_count')->default(0)->after('card_number');
            $table->string('removal_status')->nullable()->after('fingerprint_count'); // pending_approval / queued
            $table->timestamp('removal_due_at')->nullable()->after('removal_status'); // এর আগে ডিভাইস থেকে মোছা হবে না
            $table->timestamp('removed_at')->nullable()->after('removal_due_at'); // ডিভাইস থেকে মোছা হয়েছে — রো থাকে যাতে ID পুনর্ব্যবহার না হয়
            $table->timestamp('last_seen_on_device_at')->nullable()->after('removed_at');

            $table->index(['attendance_device_id', 'removed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_users', function (Blueprint $table) {
            $table->dropIndex(['attendance_device_id', 'removed_at']);
            $table->dropColumn([
                'card_number',
                'fingerprint_count',
                'removal_status',
                'removal_due_at',
                'removed_at',
                'last_seen_on_device_at',
            ]);
        });
    }
};
