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
        Schema::table('acting_admins', function (Blueprint $table) {
            // 'super_admin_acting' এর সাথে ব্যাকওয়ার্ড কম্প্যাটিবল ডিফল্ট — আগের সব রেকর্ড এই লেভেলেই ছিল
            $table->string('level')->default('super_admin_acting')->after('to_date');
        });

        Schema::table('acting_admins', function (Blueprint $table) {
            // to_date খালি রাখলে "permanent, until manually removed" বোঝাবে
            $table->date('to_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acting_admins', function (Blueprint $table) {
            $table->date('to_date')->nullable(false)->change();
            $table->dropColumn('level');
        });
    }
};
