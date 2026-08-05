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
        Schema::table('classes', function (Blueprint $table) {
            // এই ক্লাসের attendance-এর জন্য দায়িত্বপ্রাপ্ত টিচার
            $table->foreignId('class_teacher_id')
                ->nullable()
                ->after('order')
                ->constrained('teacher_profiles')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_teacher_id');
        });
    }
};
