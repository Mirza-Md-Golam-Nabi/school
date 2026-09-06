<?php

namespace App\Console\Commands;

use App\Enums\ClassLevel;
use App\Models\Classes;
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
            $sets['Pre-Primary'] = $this->prePrimaryClasses();
        }

        if ($this->option('primary')) {
            $sets['Primary'] = $this->primaryClasses();
        }

        if ($this->option('secondary')) {
            $sets['Secondary'] = $this->secondaryClasses();
        }

        if ($this->option('college')) {
            $sets['College'] = $this->collegeClasses();
        }

        return $sets;
    }

    /**
     * Pre-primary / kindergarten level — Play, Nursery and K.G. Government
     * schools don't have these, so they live behind their own --pre-primary
     * option instead of being bundled into --primary. Ordered before Class 1
     * (negative/zero order) so they sort correctly ahead of it, with no
     * section or group.
     *
     * @return array<int, array<string, mixed>>
     */
    private function prePrimaryClasses(): array
    {
        return [
            ['name' => 'Play', 'level' => ClassLevel::PrePrimary, 'order' => -2],
            ['name' => 'Nursery', 'level' => ClassLevel::PrePrimary, 'order' => -1],
            ['name' => 'K.G', 'level' => ClassLevel::PrePrimary, 'order' => 0],
        ];
    }

    /**
     * Primary level — Class 1 through Class 5, no section or group.
     *
     * @return array<int, array<string, mixed>>
     */
    private function primaryClasses(): array
    {
        return collect(range(1, 5))
            ->map(fn (int $order): array => [
                'name' => "Class {$order}",
                'level' => ClassLevel::Primary,
                'order' => $order,
            ])
            ->all();
    }

    /**
     * Secondary level — Class 6 through Class 10. All of them have sections,
     * but groups (Science/Arts/Commerce) only start from Class 9 onward.
     *
     * @return array<int, array<string, mixed>>
     */
    private function secondaryClasses(): array
    {
        return collect(range(6, 10))
            ->map(fn (int $order): array => [
                'name' => "Class {$order}",
                'level' => ClassLevel::Secondary,
                'order' => $order,
                'has_section' => true,
                'has_group' => $order >= 9,
            ])
            ->all();
    }

    /**
     * College level — Class 11 and Class 12 (HSC 1st and 2nd year),
     * groups (Science/Arts/Commerce) are required here.
     *
     * @return array<int, array<string, mixed>>
     */
    private function collegeClasses(): array
    {
        return collect(range(11, 12))
            ->map(fn (int $order): array => [
                'name' => "Class {$order}",
                'level' => ClassLevel::College,
                'order' => $order,
                'has_group' => true,
            ])
            ->all();
    }
}
