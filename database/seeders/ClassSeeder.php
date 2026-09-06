<?php

namespace Database\Seeders;

use App\Models\Classes;
use App\Models\Group;
use App\Models\Section;
use App\Models\TeacherProfile;
use App\Support\ClassDefinitions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class ClassSeeder extends Seeder
{
    public function run(): void
    {
        // Class 1–5: Primary, no section, no group
        // Class 6–8: Secondary, has section, no group
        // Class 9–10: Secondary, has section, has group
        $classConfigs = [
            ...ClassDefinitions::primary(),
            ...ClassDefinitions::secondary(),
        ];

        $groups = $this->createGroups();
        $teachers = TeacherProfile::inRandomOrder()->get();

        foreach ($classConfigs as $index => $config) {
            $hasSection = $config['has_section'] ?? false;
            $hasGroup = $config['has_group'] ?? false;

            $class = Classes::firstOrCreate(
                ['name' => $config['name']],
                [
                    'level' => $config['level'],
                    'order' => $config['order'],
                    'class_teacher_id' => $teachers->get($index)?->id,
                    'has_section' => $hasSection,
                    'has_group' => $hasGroup,
                    'is_active' => true,
                ]
            );

            if ($hasSection) {
                $this->createSections($class);
            }

            if ($hasGroup) {
                $class->groups()->sync($groups->pluck('id'));
            }
        }
    }

    /** @return Collection<int, Group> */
    private function createGroups(): Collection
    {
        $groupNames = ['Science', 'Commerce', 'Humanities'];

        foreach ($groupNames as $name) {
            Group::firstOrCreate(['name' => $name], ['is_active' => true]);
        }

        return Group::whereIn('name', $groupNames)->get();
    }

    private function createSections(Classes $class): void
    {
        foreach (['A', 'B'] as $name) {
            Section::firstOrCreate(
                ['class_id' => $class->id, 'name' => $name],
                ['capacity' => 40, 'is_active' => true]
            );
        }
    }
}
