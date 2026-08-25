<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('grade_scales', function (Blueprint $table) {
            $table->id();
            $table->string('letter_grade', 10);
            $table->decimal('min_mark', 5, 2);
            $table->decimal('max_mark', 5, 2);
            $table->decimal('grade_point', 3, 2);
            $table->string('color', 20)->default('gray');
            $table->timestamps();

            $table->index('min_mark');
        });

        // Seed the default grading scale here (instead of the old hardcoded
        // App\Enums\Grade) so every environment — including fresh test
        // databases via RefreshDatabase — has a working scale out of the box.
        DB::table('grade_scales')->insert([
            ['letter_grade' => 'A+', 'min_mark' => 80, 'max_mark' => 100, 'grade_point' => 5.00, 'color' => 'success', 'created_at' => now(), 'updated_at' => now()],
            ['letter_grade' => 'A', 'min_mark' => 70, 'max_mark' => 79, 'grade_point' => 4.00, 'color' => 'success', 'created_at' => now(), 'updated_at' => now()],
            ['letter_grade' => 'A-', 'min_mark' => 60, 'max_mark' => 69, 'grade_point' => 3.50, 'color' => 'info', 'created_at' => now(), 'updated_at' => now()],
            ['letter_grade' => 'B', 'min_mark' => 50, 'max_mark' => 59, 'grade_point' => 3.00, 'color' => 'info', 'created_at' => now(), 'updated_at' => now()],
            ['letter_grade' => 'C', 'min_mark' => 40, 'max_mark' => 49, 'grade_point' => 2.00, 'color' => 'warning', 'created_at' => now(), 'updated_at' => now()],
            ['letter_grade' => 'D', 'min_mark' => 33, 'max_mark' => 39, 'grade_point' => 1.00, 'color' => 'danger', 'created_at' => now(), 'updated_at' => now()],
            ['letter_grade' => 'F', 'min_mark' => 0, 'max_mark' => 32, 'grade_point' => 0.00, 'color' => 'danger', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_scales');
    }
};
