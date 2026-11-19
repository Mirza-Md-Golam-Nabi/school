<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * প্রতিটা SMS প্রোভাইডারের API যে response code ফেরত দেয় আর তার মানে।
     */
    public function up(): void
    {
        Schema::create('sms_vendor_response_codes', function (Blueprint $table) {
            $table->id();
            $table->string('driver'); // কোন প্রোভাইডারের কোড (SmsVendorDriver), যেমন: bulksmsbd
            $table->string('code'); // যেমন: 202, 1007
            $table->string('message'); // কোডের মানে, যেমন: Balance Insufficient
            $table->boolean('is_success')->default(false); // true = SMS জমা হয়েছে
            $table->timestamps();

            $table->unique(['driver', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_vendor_response_codes');
    }
};
