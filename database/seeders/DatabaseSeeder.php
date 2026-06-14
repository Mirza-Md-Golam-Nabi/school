<?php

namespace Database\Seeders;

use Database\Seeders\ActingAdminRoleSeeder;
use Database\Seeders\ClassSeeder;
use Database\Seeders\ClassSubjectSeeder;
use Database\Seeders\ExamConfigSeeder;
use Database\Seeders\ExamSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubjectSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            ActingAdminRoleSeeder::class,
            UserSeeder::class,
            ClassSeeder::class,
            SubjectSeeder::class,
            ClassSubjectSeeder::class,
            ExamConfigSeeder::class,
            ExamSeeder::class,
        ]);
    }
}
