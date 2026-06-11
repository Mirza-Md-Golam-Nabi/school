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
        Schema::create('acting_admins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users'); // যাকে দায়িত্ব দেওয়া হচ্ছে
            $table->foreignId('assigned_by')->constrained('users'); // Super Admin
            $table->date('from_date');
            $table->date('to_date');
            $table->boolean('is_active')->default(true);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acting_admins');
    }
};
