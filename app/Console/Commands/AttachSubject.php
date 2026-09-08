<?php

namespace App\Console\Commands;

use App\Enums\ClassLevel;
use App\Enums\SubjectType;
use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Models\Group;
use App\Models\Subject;
use App\Support\ClassGroupSubjectDefinitions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('attach:subject
    {--primary : Attach the compulsory subjects for Class 1-5 (no groups)}
    {--class-6-8 : Attach the compulsory subjects for Class 6-8 (no groups)}
    {--secondary : Attach the Class 9-10 group-subject mapping (Science/Commerce/Humanities)}'
)]
#[Description('Creates the Science/Commerce/Humanities groups, links them to the relevant classes, and attaches each subject to its class+group using firstOrCreate — existing attachments are never recreated.')]
class AttachSubject extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('primary') && ! $this->option('class-6-8') && ! $this->option('secondary')) {
            $this->error('Please provide at least one option: --primary, --class-6-8 or --secondary');

            return self::FAILURE;
        }

        $attached = 0;
        $skipped = 0;

        if ($this->option('primary')) {
            $result = $this->attachPrimary();

            if ($result === null) {
                return self::FAILURE;
            }

            [$a, $s] = $result;
            $attached += $a;
            $skipped += $s;
        }

        if ($this->option('class-6-8')) {
            $result = $this->attachClassSixToEight();

            if ($result === null) {
                return self::FAILURE;
            }

            [$a, $s] = $result;
            $attached += $a;
            $skipped += $s;
        }

        if ($this->option('secondary')) {
            $result = $this->attachSecondary();

            if ($result === null) {
                return self::FAILURE;
            }

            [$a, $s] = $result;
            $attached += $a;
            $skipped += $s;
        }

        $this->newLine();
        $this->info("Done — {$attached} attached, {$skipped} already existed.");

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function attachPrimary(): ?array
    {
        $classes = $this->resolveClasses(ClassLevel::Primary, hasGroup: false, label: 'Class 1-5');

        if ($classes === null) {
            return null;
        }

        $this->info('Attaching Class 1-5 compulsory subjects...');

        return $this->attachMapping(ClassGroupSubjectDefinitions::primary(), $classes, collect());
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function attachClassSixToEight(): ?array
    {
        $classes = $this->resolveClasses(ClassLevel::Secondary, hasGroup: false, label: 'Class 6-8');

        if ($classes === null) {
            return null;
        }

        $this->info('Attaching Class 6-8 compulsory subjects...');

        return $this->attachMapping(ClassGroupSubjectDefinitions::classSixToEight(), $classes, collect());
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function attachSecondary(): ?array
    {
        $classes = $this->resolveClasses(ClassLevel::Secondary, hasGroup: true, label: 'Class 9-10');

        if ($classes === null) {
            return null;
        }

        $this->info('Attaching Class 9-10 group subjects...');

        $groups = $this->resolveGroups($classes);

        return $this->attachMapping(ClassGroupSubjectDefinitions::secondary(), $classes, $groups);
    }

    private function resolveClasses(ClassLevel $level, bool $hasGroup, string $label): ?Collection
    {
        $classes = Classes::query()
            ->where('level', $level)
            ->where('has_group', $hasGroup)
            ->get();

        if ($classes->isEmpty()) {
            $this->error("No {$label} classes found. Run `create:class` for that level first.");

            return null;
        }

        return $classes;
    }

    /**
     * @param  array<int, array{group: ?string, subject_type: SubjectType, subjects: array<int, string>}>  $mapping
     * @param  Collection<string, Group>  $groups
     * @return array{0: int, 1: int}
     */
    private function attachMapping(array $mapping, Collection $classes, Collection $groups): array
    {
        $attached = 0;
        $skipped = 0;

        foreach ($mapping as $entry) {
            $groupLabel = $entry['group'] ?? 'All Groups';
            $groupId = $entry['group'] === null ? null : $groups[$entry['group']]->id;

            foreach ($entry['subjects'] as $subjectName) {
                $subject = Subject::where('name', $subjectName)->first();

                if (! $subject) {
                    $this->warn("  ! Subject not found, skipping: {$subjectName}");

                    continue;
                }

                foreach ($classes as $class) {
                    $record = ClassGroupSubject::firstOrCreate(
                        [
                            'class_id' => $class->id,
                            'group_id' => $groupId,
                            'subject_id' => $subject->id,
                        ],
                        ['subject_type' => $entry['subject_type']]
                    );

                    if ($record->wasRecentlyCreated) {
                        $attached++;
                        $this->line("  + Attached: {$subjectName} → {$class->name} ({$groupLabel})");
                    } else {
                        $skipped++;
                    }
                }
            }
        }

        return [$attached, $skipped];
    }

    /**
     * Ensures the Science/Commerce/Humanities groups exist and are linked to
     * the given classes, returning them keyed by name.
     *
     * @return Collection<string, Group>
     */
    private function resolveGroups(Collection $classes): Collection
    {
        return collect(ClassGroupSubjectDefinitions::SECONDARY_GROUPS)
            ->mapWithKeys(function (string $name) use ($classes) {
                $group = Group::firstOrCreate(['name' => $name], ['is_active' => true]);
                $group->classes()->syncWithoutDetaching($classes->pluck('id'));

                return [$name => $group];
            });
    }
}
