<?php

use App\Enums\FineType;
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
        Schema::create('late_fee_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_type_id')->constrained('fee_types')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->tinyInteger('grace_days')->unsigned()->default(0); // days after due_day before fine applies
            $table->enum('fine_type', array_column(FineType::cases(), 'value')); // percent, fixed
            $table->decimal('fine_value', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['fee_type_id', 'class_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('late_fee_rules');
    }
};
