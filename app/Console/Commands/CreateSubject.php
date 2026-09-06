<?php

namespace App\Console\Commands;

use App\Models\Subject;
use App\Support\SubjectDefinitions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('create:subject
    {--primary : Create the primary level subjects (Class 1-5)}
    {--secondary : Create the secondary level subjects (Class 6-10 / SSC)}
    {--college : Create the college level subjects (Class 11-12 / HSC)}'
)]
#[Description('Creates the default subject list for each level (primary/secondary/college) using firstOrCreate — existing subjects are never recreated.')]
class CreateSubject extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $subjectSets = $this->resolveRequestedSubjectSets();

        if ($subjectSets === []) {
            $this->error('Please provide at least one option: --primary, --secondary or --college');

            return self::FAILURE;
        }

        $created = 0;
        $skipped = 0;

        foreach ($subjectSets as $level => $subjects) {
            $this->info("Creating {$level} subjects...");

            foreach ($subjects as $subject) {
                $record = Subject::firstOrCreate(
                    ['name' => $subject['name']],
                    [
                        'code' => $subject['code'] ?? null,
                        'has_mcq' => $subject['has_mcq'] ?? true,
                        'has_written' => $subject['has_written'] ?? true,
                        'has_practical' => $subject['has_practical'] ?? false,
                        'is_active' => true,
                    ]
                );

                if ($record->wasRecentlyCreated) {
                    $created++;
                    $this->line("  + Created: {$subject['name']}");
                } else {
                    $skipped++;
                    $this->line("  - Already exists: {$subject['name']}");
                }
            }
        }

        $this->newLine();
        $this->info("Done — {$created} created, {$skipped} already existed.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function resolveRequestedSubjectSets(): array
    {
        $sets = [];

        if ($this->option('primary')) {
            $sets['Primary'] = SubjectDefinitions::primary();
        }

        if ($this->option('secondary')) {
            $sets['Secondary'] = SubjectDefinitions::secondary();
        }

        if ($this->option('college')) {
            $sets['College'] = SubjectDefinitions::college();
        }

        return $sets;
    }
}
