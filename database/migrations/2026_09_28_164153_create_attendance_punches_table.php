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
        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_device_id')->constrained()->cascadeOnDelete();
            $table->string('enroll_id', 50);
            $table->dateTime('punched_at'); // স্কুলের timezone-এ (app.timezone)
            $table->unsignedSmallInteger('verify_type')->nullable(); // আঙুল / কার্ড / পাসওয়ার্ড ইত্যাদি
            $table->unsignedSmallInteger('state')->nullable(); // ডিভাইসের in/out স্টেট (বিশ্বাসযোগ্য নয়, শুধু সংরক্ষণ)
            $table->timestamp('processed_at')->nullable(); // attendances-এ রূপান্তরের সময়
            $table->timestamps();

            // একই পাঞ্চ pull ও live দুই পথে এলেও একবারই থাকবে
            $table->unique(['attendance_device_id', 'enroll_id', 'punched_at'], 'attendance_punches_unique');
            $table->index(['attendance_device_id', 'processed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_punches');
    }
};
