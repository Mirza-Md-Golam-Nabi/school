<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source_type');   // attendance, notice, other
            $table->unsignedBigInteger('source_id')->nullable(); // attendance_id or notice_id
            $table->string('phone');
            $table->text('message');
            $table->string('status')->default('pending'); // sent, failed, pending
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
