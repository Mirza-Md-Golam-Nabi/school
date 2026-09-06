<?php

namespace App\Console\Commands;

use App\Models\Classes;
use App\Support\ClassDefinitions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('create:class
    {--pre-primary : Create the pre-primary/kindergarten classes (Play, Nursery, K.G) — only for kindergarten schools}
    {--primary : Create the primary level classes (Class 1-5)}
    {--secondary : Create the secondary level classes (Class 6-10 / SSC)}
    {--college : Create the college level classes (Class 11-12 / HSC)}'
)]
#[Description('Creates the default class list for each level (pre-primary/primary/secondary/college) using firstOrCreate — existing classes are never recreated.')]
class CreateClass extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $classSets = $this->resolveRequestedClassSets();

        if ($classSets === []) {
            $this->error('Please provide at least one option: --pre-primary, --primary, --secondary or --college');

            return self::FAILURE;
        }

        $created = 0;
        $skipped = 0;

        foreach ($classSets as $level => $classes) {
            $this->info("Creating {$level} classes...");

            foreach ($classes as $class) {
                $record = Classes::firstOrCreate(
                    ['name' => $class['name']],
                    [
                        'level' => $class['level'],
                        'order' => $class['order'],
                        'has_section' => $class['has_section'] ?? false,
                        'has_group' => $class['has_group'] ?? false,
                        'is_active' => true,
                    ]
                );

                if ($record->wasRecentlyCreated) {
                    $created++;
                    $this->line("  + Created: {$class['name']}");
                } else {
                    $skipped++;
                    $this->line("  - Already exists: {$class['name']}");
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
    private function resolveRequestedClassSets(): array
    {
        $sets = [];

        if ($this->option('pre-primary')) {
            $sets['Pre-Primary'] = ClassDefinitions::prePrimary();
        }

        if ($this->option('primary')) {
            $sets['Primary'] = ClassDefinitions::primary();
        }

        if ($this->option('secondary')) {
            $sets['Secondary'] = ClassDefinitions::secondary();
        }

        if ($this->option('college')) {
            $sets['College'] = ClassDefinitions::college();
        }

        return $sets;
    }
}
