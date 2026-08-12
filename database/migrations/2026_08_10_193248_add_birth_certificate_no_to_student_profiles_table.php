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
        Schema::table('student_profiles', function (Blueprint $table) {
            // Nullable so existing rows aren't broken; required going forward via form validation.
            $table->string('birth_certificate_no')->nullable()->unique()->after('registration_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropUnique(['birth_certificate_no']);
            $table->dropColumn('birth_certificate_no');
        });
    }
};
