<?php

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->morphs('attendable');               // attendable_type, attendable_id
            $table->date('date');
            $table->time('entry_time')->nullable();
            $table->time('exit_time')->nullable();
            $table->string('status')->default(AttendanceStatus::Absent->value);
            $table->string('source')->default(AttendanceSource::Manual->value);
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['attendable_type', 'attendable_id', 'date']);
            $table->unique(['attendable_type', 'attendable_id', 'date', 'class_id', 'subject_id'], 'attendances_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
