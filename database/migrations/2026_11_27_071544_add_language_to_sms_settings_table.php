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
        Schema::table('sms_settings', function (Blueprint $table) {
            $table->string('language', 5)->default('bn')->after('templates'); // SMS-এর ভাষা — bn: বাংলা, en: ইংরেজি (ডিফল্ট টেমপ্লেট এই ভাষায়)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sms_settings', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
};
