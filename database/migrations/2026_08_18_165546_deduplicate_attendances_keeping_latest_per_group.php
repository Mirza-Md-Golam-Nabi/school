<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A "date" cast without an explicit format used to write attendance dates
     * with a spurious time component, which broke the exact-string date match
     * inside Attendance::updateOrCreate() and caused a brand new row to be
     * inserted on every re-save instead of updating the existing one. This
     * cleans up the resulting duplicates, keeping only the most recent row
     * (highest id) per attendable/date/class/subject group.
     */
    public function up(): void
    {
        $groups = DB::table('attendances')
            ->select(['attendable_type', 'attendable_id', 'date', 'class_id', 'subject_id'])
            ->groupBy(['attendable_type', 'attendable_id', 'date', 'class_id', 'subject_id'])
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $ids = DB::table('attendances')
                ->where('attendable_type', $group->attendable_type)
                ->where('attendable_id', $group->attendable_id)
                ->where('date', $group->date)
                ->where('class_id', $group->class_id)
                ->where('subject_id', $group->subject_id)
                ->orderByDesc('id')
                ->pluck('id');

            $idsToDelete = $ids->slice(1);

            if ($idsToDelete->isNotEmpty()) {
                DB::table('attendances')->whereIn('id', $idsToDelete)->delete();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Deleted duplicates cannot be reconstructed; nothing to reverse.
    }
};
