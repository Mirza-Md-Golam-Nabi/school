<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('exams')->select(['id', 'start_date', 'end_date'])->orderBy('id')->each(function (object $exam): void {
            DB::table('exams')->where('id', $exam->id)->update([
                'start_date' => Carbon::parse($exam->start_date)->toDateString(),
                'end_date' => Carbon::parse($exam->end_date)->toDateString(),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data normalization only; no schema change to reverse.
    }
};
