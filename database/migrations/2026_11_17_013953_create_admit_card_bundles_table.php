<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per exam: a ready-made combined admit card PDF of the exam's
     * class, built by a queue job whenever its admit cards are (re)generated,
     * so downloading it never makes the admin wait.
     */
    public function up(): void
    {
        Schema::create('admit_card_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->unique()->constrained('exams')->cascadeOnDelete();
            $table->string('file_path')->nullable();
            // ফাইল তৈরির সময় কয়টা admit card ছিল — পরে কম/বেশি হলে ফাইলটা পুরনো ধরা হবে
            $table->unsignedInteger('card_count')->default(0);
            $table->boolean('is_generated')->default(false);
            // প্রতিবার নতুন generate-এ নতুন token — পুরনো job যেন নতুন ফাইল overwrite না করে
            $table->uuid('generation_token')->nullable();
            $table->timestamp('file_generated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admit_card_bundles');
    }
};
