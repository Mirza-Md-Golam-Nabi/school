<?php

namespace Database\Seeders;

use App\Enums\ExamConfigType;
use App\Models\Classes;
use App\Models\ExamType;
use App\Models\FeeStructure;
use App\Models\FeeType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class FeeStructureSeeder extends Seeder
{
    private const TUITION_BASE_AMOUNT = 100;

    private const TUITION_STEP = 10;

    private const TUITION_DUE_DAY = 10;

    private const EXAM_FEE_DUE_DAY = 15;

    /** @var array<string, int> */
    private array $examFeeAmounts = [
        'supporting' => 25,
        'not_supporting' => 50,
        'main' => 100,
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sessionYear = (int) now()->year;
        $classes = Classes::active()->orderBy('order')->get();

        if ($classes->isEmpty()) {
            return;
        }

        $this->seedTuitionFee($classes, $sessionYear);
        $this->seedExamFees($classes, $sessionYear);
    }

    /**
     * @param  Collection<int, Classes>  $classes
     */
    private function seedTuitionFee(Collection $classes, int $sessionYear): void
    {
        $tuitionFeeType = FeeType::where('name', 'Tuition Fee')->first();

        if (! $tuitionFeeType) {
            return;
        }

        foreach ($classes as $class) {
            $amount = self::TUITION_BASE_AMOUNT + (($class->order - 1) * self::TUITION_STEP);

            FeeStructure::firstOrCreate(
                [
                    'class_id' => $class->id,
                    'fee_type_id' => $tuitionFeeType->id,
                    'session_year' => $sessionYear,
                ],
                [
                    'amount' => $amount,
                    'due_day' => self::TUITION_DUE_DAY,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * @param  Collection<int, Classes>  $classes
     */
    private function seedExamFees(Collection $classes, int $sessionYear): void
    {
        $examTypes = ExamType::with('examTypeConfig')->get();

        foreach ($examTypes as $examType) {
            $configType = $examType->examTypeConfig?->type;

            if (! $configType instanceof ExamConfigType) {
                continue;
            }

            $amount = $this->examFeeAmounts[$configType->value] ?? null;

            if ($amount === null) {
                continue;
            }

            $feeType = FeeType::where('name', "Exam Fee - {$examType->name}")->first();

            if (! $feeType) {
                continue;
            }

            foreach ($classes as $class) {
                FeeStructure::firstOrCreate(
                    [
                        'class_id' => $class->id,
                        'fee_type_id' => $feeType->id,
                        'session_year' => $sessionYear,
                    ],
                    [
                        'amount' => $amount,
                        'due_day' => self::EXAM_FEE_DUE_DAY,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
