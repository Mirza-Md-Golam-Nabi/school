<?php

namespace Database\Factories;

use App\Enums\MergedAbsentRule;
use App\Enums\MergedComponentPassRule;
use App\Enums\MergedPassRule;
use App\Enums\ResultRounding;
use App\Models\ExamSubjectMerge;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSubjectMerge>
 */
class ExamSubjectMergeFactory extends Factory
{
    /**
     * Define the model's default state. `exam_id` must be given.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'name_bn' => null,
            'first_subject_id' => fn (): int => Subject::create(['name' => "{$name} 1st Paper", 'has_written' => true, 'is_active' => true])->id,
            'second_subject_id' => fn (): int => Subject::create(['name' => "{$name} 2nd Paper", 'has_written' => true, 'is_active' => true])->id,
            'pass_rule' => MergedPassRule::Combined,
            'component_pass_rule' => MergedComponentPassRule::Combined,
            'absent_rule' => MergedAbsentRule::Fail,
            'rounding' => ResultRounding::Decimal,
        ];
    }
}
