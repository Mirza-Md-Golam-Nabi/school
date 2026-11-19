<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('driver'); // bulksmsbd — কোন vendor-এর API
            $table->text('api_key')->nullable(); // encrypted
            $table->string('sender_id')->nullable(); // যেমন: 8809617613451
            $table->unsignedSmallInteger('priority')->default(1); // ছোট সংখ্যা আগে চেষ্টা হয়
            $table->boolean('is_active')->default(false); // এডমিন চালু/বন্ধ করতে পারে
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_vendors');
    }
};
