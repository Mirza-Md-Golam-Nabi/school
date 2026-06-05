<?php

use App\Enums\NoticeTargetType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('body');
            $table->string('target_type')->default(NoticeTargetType::All->value);
            $table->boolean('send_sms')->default(false);
            $table->timestamp('published_at')->nullable(); // null = draft, future = scheduled
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('target_type');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notices');
    }
};
