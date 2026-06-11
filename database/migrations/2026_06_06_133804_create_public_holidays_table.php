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
        Schema::create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['single', 'range'])->default('single');
            $table->date('start_date');
            $table->date('end_date')->nullable(); // শুধু range type-এর জন্য
            $table->boolean('is_recurring')->default(false); // প্রতি বছর একই তারিখে হয় কিনা
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_holidays');
    }
};
