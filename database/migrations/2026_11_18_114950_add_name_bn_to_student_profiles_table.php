<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            // বাংলা নাম — ফর্মে required, কিন্তু পুরনো student-দের নেই বলে DB-তে nullable
            $table->string('name_bn')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn('name_bn');
        });
    }
};
