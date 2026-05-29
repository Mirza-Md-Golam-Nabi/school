<?php

use App\Enums\EmploymentStatus;
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
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gender')->nullable();         // male, female, other
            $table->date('date_of_birth')->nullable();
            $table->string('blood_group')->nullable();    // A+, A-, B+, B-, AB+, AB-, O+, O-
            $table->string('religion')->nullable();       // muslim, hindu, christian, buddhist, other
            $table->string('nationality')->default('Bangladeshi');
            $table->string('designation')->nullable();
            $table->date('joining_date')->nullable();
            $table->string('status')->default(EmploymentStatus::Active->value);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
