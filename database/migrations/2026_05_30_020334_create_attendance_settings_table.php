<?php

use App\Enums\AttendanceMode;
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
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->string('attendance_mode')->default(AttendanceMode::Daily->value);
            $table->unsignedSmallInteger('late_threshold_minutes')->default(15);
            $table->time('entry_time')->default('08:00:00');
            $table->time('exit_time')->default('14:00:00');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_settings');
    }
};
