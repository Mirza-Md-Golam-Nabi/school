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
        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->morphs('profileable'); // teacher_profiles, staff_profiles
            $table->boolean('use_components')->default(true); // false হলে flat_amount ব্যবহার হবে
            $table->decimal('flat_amount', 10, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable(); // salary change হলে আগের row-এ বসবে
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_structures');
    }
};
