<?php

use App\Enums\StudentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedMediumInteger('roll_no')->index();
            $table->unsignedMediumInteger('registration_no')->nullable()->index();
            $table->foreignId('current_class_id')
                ->nullable()
                ->constrained('classes')
                ->nullOnDelete();
            $table->foreignId('current_section_id')
                ->nullable()
                ->constrained('sections')
                ->nullOnDelete();
            $table->foreignId('current_group_id')
                ->nullable()
                ->constrained('groups')
                ->nullOnDelete();
            $table->unsignedSmallInteger('session_year');
            $table->string('gender');
            $table->date('date_of_birth')->nullable();
            $table->string('blood_group')->nullable();
            $table->string('religion')->nullable();
            $table->string('nationality')->default('Bangladeshi');
            $table->string('father_name')->nullable();
            $table->string('father_occupation')->nullable();
            $table->string('father_photo')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('mother_occupation')->nullable();
            $table->string('mother_photo')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_relation')->nullable();
            $table->string('guardian_occupation')->nullable();
            $table->string('guardian_photo')->nullable();
            $table->date('admission_date')->nullable();
            $table->string('status')->default(StudentStatus::Active->value);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
