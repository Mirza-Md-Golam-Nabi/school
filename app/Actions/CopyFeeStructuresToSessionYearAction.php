<?php

namespace App\Actions;

use App\Models\FeeStructure;

class CopyFeeStructuresToSessionYearAction
{
    /**
     * একটা সেশনের সব active fee structure নতুন সেশন বছরে কপি করে — পুরনো
     * সেশনের রো অক্ষত থাকে, যাতে দুই সেশনের ইনভয়েস পাশাপাশি চালানো যায়।
     * $classId দিলে শুধু সেই ক্লাসের, null হলে সব ক্লাসের। নতুন বছরে একই
     * ক্লাস ও fee type-এর structure আগে থেকে থাকলে সেটা বাদ যায়
     * (class + fee type + session year unique)।
     *
     * @return array{copied: int, skipped: int}
     */
    public function handle(int $fromYear, int $toYear, ?int $classId = null): array
    {
        if ($fromYear === $toYear) {
            return ['copied' => 0, 'skipped' => 0];
        }

        $existingKeys = FeeStructure::where('session_year', $toYear)
            ->when($classId, fn ($query) => $query->where('class_id', $classId))
            ->get(['class_id', 'fee_type_id'])
            ->mapWithKeys(fn (FeeStructure $structure): array => ["{$structure->class_id}-{$structure->fee_type_id}" => true]);

        $copied = 0;
        $skipped = 0;

        FeeStructure::where('session_year', $fromYear)
            ->where('is_active', true)
            ->when($classId, fn ($query) => $query->where('class_id', $classId))
            ->get()
            ->each(function (FeeStructure $structure) use ($toYear, $existingKeys, &$copied, &$skipped): void {
                if ($existingKeys->has("{$structure->class_id}-{$structure->fee_type_id}")) {
                    $skipped++;

                    return;
                }

                FeeStructure::create([
                    'class_id' => $structure->class_id,
                    'fee_type_id' => $structure->fee_type_id,
                    'amount' => $structure->amount,
                    'due_day' => $structure->due_day,
                    'session_year' => $toYear,
                    'is_active' => true,
                ]);

                $copied++;
            });

        return ['copied' => $copied, 'skipped' => $skipped];
    }
}
