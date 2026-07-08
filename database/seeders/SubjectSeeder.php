<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /** @return array<int, array{name: string, code: string, has_mcq: bool}> */
    public static function subjects(): array
    {
        return [
            ['code' => 'BAN', 'has_mcq' => true, 'name' => 'Bangla'],
            ['code' => 'ENG', 'has_mcq' => true, 'name' => 'English'],
            ['code' => 'MAT', 'has_mcq' => false, 'name' => 'Mathematics'],
            ['code' => 'SCI', 'has_mcq' => true, 'name' => 'Science'],
            ['code' => 'BGS', 'has_mcq' => false, 'name' => 'Bangladesh and Global Studies'],
        ];
    }

    public function run(): void
    {
        foreach (self::subjects() as $subject) {
            Subject::firstOrCreate(
                ['name' => $subject['name']],
                [
                    'code' => $subject['code'],
                    'has_mcq' => $subject['has_mcq'],
                    'has_written' => true,
                    'has_practical' => false,
                    'is_active' => true,
                ]
            );
        }
    }
}
