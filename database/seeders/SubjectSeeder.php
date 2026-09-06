<?php

namespace Database\Seeders;

use App\Models\Subject;
use App\Support\SubjectDefinitions;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Every subject across all levels (primary/secondary/college), deduped
     * by name — subjects like Bangla or Physics appear in more than one
     * level's list but must resolve to a single Subject record.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function subjects(): array
    {
        return collect([
            ...SubjectDefinitions::primary(),
            ...SubjectDefinitions::secondary(),
            ...SubjectDefinitions::college(),
        ])
            ->unique('name')
            ->values()
            ->all();
    }

    public function run(): void
    {
        foreach (self::subjects() as $subject) {
            Subject::firstOrCreate(
                ['name' => $subject['name']],
                [
                    'code' => $subject['code'] ?? null,
                    'has_mcq' => $subject['has_mcq'] ?? true,
                    'has_written' => $subject['has_written'] ?? true,
                    'has_practical' => $subject['has_practical'] ?? false,
                    'is_active' => true,
                ]
            );
        }
    }
}
