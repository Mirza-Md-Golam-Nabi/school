<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vendor-গুলো এখন সেন্ট্রাল অ্যাপ (NextFusion) থেকে আসে — এই কলাম দিয়ে
     * বোঝা যায় কোন vendor সেন্ট্রালের কোনটার কপি।
     */
    public function up(): void
    {
        Schema::table('sms_vendors', function (Blueprint $table) {
            $table->unsignedBigInteger('central_id')->nullable()->unique()->after('id'); // NextFusion-এর sms_vendors.id; null = সেন্ট্রাল থেকে আসেনি
        });
    }

    public function down(): void
    {
        Schema::table('sms_vendors', function (Blueprint $table) {
            $table->dropUnique(['central_id']);
            $table->dropColumn('central_id');
        });
    }
};
