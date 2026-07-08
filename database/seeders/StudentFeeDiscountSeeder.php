<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\Classes;
use App\Models\FeeDiscount;
use App\Models\FeeType;
use App\Models\StudentFeeDiscount;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class StudentFeeDiscountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Discounts only apply to the "Tuition Fee" fee type, never exam fees.
     */
    public function run(): void
    {
        $tuitionFeeType = FeeType::where('name', 'Tuition Fee')->first();
        $discounts = FeeDiscount::all();

        if (! $tuitionFeeType || $discounts->isEmpty()) {
            return;
        }

        $sessionYear = (int) now()->year;
        $approvedBy = User::where('user_type', UserType::Admin)->value('id')
            ?? User::query()->value('id');

        $classes = Classes::active()->orderBy('order')->get();

        foreach ($classes as $class) {
            $students = StudentProfile::where('current_class_id', $class->id)->active()->get();

            if ($students->isEmpty()) {
                continue;
            }

            $count = min($students->count(), fake()->numberBetween(1, 2));

            foreach ($students->random($count) as $student) {
                $discount = $discounts->random();

                StudentFeeDiscount::firstOrCreate(
                    [
                        'student_id' => $student->id,
                        'fee_type_id' => $tuitionFeeType->id,
                        'session_year' => $sessionYear,
                    ],
                    [
                        'discount_id' => $discount->id,
                        'approved_by' => $approvedBy,
                        'remarks' => "{$discount->name} approved for session {$sessionYear}.",
                    ]
                );
            }
        }
    }
}
