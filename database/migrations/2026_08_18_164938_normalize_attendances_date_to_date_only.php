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
        DB::table('attendances')->select(['id', 'date'])->orderBy('id')->each(function (object $attendance): void {
            DB::table('attendances')->where('id', $attendance->id)->update([
                'date' => Carbon::parse($attendance->date)->toDateString(),
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
