<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * এই স্কুলের SMS সেটিং — একটাই row থাকে।
     */
    public function up(): void
    {
        Schema::create('sms_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false); // সব SMS-এর মূল সুইচ
            $table->decimal('rate_per_sms', 8, 4)->default(0); // প্রতি SMS পার্টের চার্জ (টাকা)
            $table->decimal('balance', 14, 4)->default(0); // স্কুলের নিজস্ব SMS ব্যালেন্স (টাকা) — vendor-এর আসল ব্যালেন্স নয়
            $table->json('enabled_events')->nullable(); // কোন কোন ঘটনায় SMS যাবে (SmsEvent values)
            $table->json('templates')->nullable(); // ঘটনা অনুযায়ী এডমিনের লেখা টেমপ্লেট
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_settings');
    }
};
