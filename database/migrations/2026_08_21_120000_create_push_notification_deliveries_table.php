<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->morphs('notifiable');
            $table->json('payload');
            $table->unsignedInteger('attempts')->default(1);
            $table->timestamp('last_sent_at');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notification_deliveries');
    }
};
