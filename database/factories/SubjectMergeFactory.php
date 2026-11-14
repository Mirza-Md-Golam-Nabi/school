<?php

namespace Database\Factories;

use App\Models\Classes;
use App\Models\Subject;
use App\Models\SubjectMerge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubjectMerge>
 */
class SubjectMergeFactory extends Factory
{
    /**
     * Define the model's default state. Rules are left empty, so School
     * Settings' defaults apply.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'class_id' => fn (): int => Classes::create(['name' => 'Class '.fake()->unique()->numberBetween(1, 9999), 'order' => 1, 'is_active' => true])->id,
            'name' => ucfirst($name),
            'name_bn' => null,
            'first_subject_id' => fn (): int => Subject::create(['name' => "{$name} 1st Paper", 'has_written' => true, 'is_active' => true])->id,
            'second_subject_id' => fn (): int => Subject::create(['name' => "{$name} 2nd Paper", 'has_written' => true, 'is_active' => true])->id,
            'pass_rule' => null,
            'component_pass_rule' => null,
            'absent_rule' => null,
            'rounding' => null,
        ];
    }
}
