<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // recharge = রিচার্জ, debit = SMS খরচ, refund = ব্যর্থ SMS-এর টাকা ফেরত
            $table->decimal('amount', 14, 4); // রিচার্জ/ফেরত হলে ধনাত্মক, খরচ হলে ঋণাত্মক
            $table->decimal('balance_after', 14, 4);
            $table->unsignedInteger('sms_parts')->default(0);
            $table->string('description')->nullable();
            $table->string('reference')->nullable()->unique(); // বাইরের রিচার্জের রেফারেন্স — একই রিচার্জ দুবার যোগ হওয়া ঠেকায়
            $table->uuid('batch_id')->nullable()->index();
            $table->foreignId('sms_recharge_request_id')->nullable();
            $table->timestamps();

            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_balance_transactions');
    }
};
