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
            // অফিসিয়াল ছবি — admin আপলোড করে; ID card, admit card ইত্যাদিতে ছাপা হয়।
            // student নিজে যে ছবি দেয় (users.avatar) সেটা আলাদা, শুধু তার নিজের জন্য।
            $table->string('photo')->nullable()->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }
};
