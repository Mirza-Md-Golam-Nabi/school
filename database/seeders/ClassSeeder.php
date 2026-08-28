<?php

namespace Database\Seeders;

use App\Enums\ClassLevel;
use App\Models\Classes;
use App\Models\Group;
use App\Models\Section;
use App\Models\TeacherProfile;
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
            ['number' => 1,  'level' => ClassLevel::Primary,   'has_section' => false, 'has_group' => false],
            ['number' => 2,  'level' => ClassLevel::Primary,   'has_section' => false, 'has_group' => false],
            ['number' => 3,  'level' => ClassLevel::Primary,   'has_section' => false, 'has_group' => false],
            ['number' => 4,  'level' => ClassLevel::Primary,   'has_section' => false, 'has_group' => false],
            ['number' => 5,  'level' => ClassLevel::Primary,   'has_section' => false, 'has_group' => false],
            ['number' => 6,  'level' => ClassLevel::Secondary, 'has_section' => true,  'has_group' => false],
            ['number' => 7,  'level' => ClassLevel::Secondary, 'has_section' => true,  'has_group' => false],
            ['number' => 8,  'level' => ClassLevel::Secondary, 'has_section' => true,  'has_group' => false],
            ['number' => 9,  'level' => ClassLevel::Secondary, 'has_section' => true,  'has_group' => true],
            ['number' => 10, 'level' => ClassLevel::Secondary, 'has_section' => true,  'has_group' => true],
        ];

        $groups = $this->createGroups();
        $teachers = TeacherProfile::inRandomOrder()->get();

        foreach ($classConfigs as $index => $config) {
            $class = Classes::firstOrCreate(
                ['name' => 'Class '.$config['number']],
                [
                    'level' => $config['level'],
                    'order' => $config['number'],
                    'class_teacher_id' => $teachers->get($index)?->id,
                    'has_section' => $config['has_section'],
                    'has_group' => $config['has_group'],
                    'is_active' => true,
                ]
            );

            if ($config['has_section']) {
                $this->createSections($class);
            }

            if ($config['has_group']) {
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
