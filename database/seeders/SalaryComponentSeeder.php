<?php

namespace Database\Seeders;

use App\Enums\SalaryComponentType;
use App\Models\SalaryComponent;
use Illuminate\Database\Seeder;

class SalaryComponentSeeder extends Seeder
{
    /** @var array<string, SalaryComponentType> */
    private array $components = [
        'Basic Salary' => SalaryComponentType::Allowance,
        'House Rent Allowance' => SalaryComponentType::Allowance,
        'Medical Allowance' => SalaryComponentType::Allowance,
        'Provident Fund' => SalaryComponentType::Deduction,
    ];

    /**
     * Run the database seeds.
     *
     * These components are made available for admins to build a component-wise
     * salary structure later — the structures SalaryStructureSeeder creates for
     * existing teachers/staff use a flat amount instead, not these components.
     */
    public function run(): void
    {
        foreach ($this->components as $name => $type) {
            SalaryComponent::firstOrCreate(
                ['name' => $name],
                ['type' => $type, 'is_active' => true]
            );
        }
    }
}
