<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Teacher\Pages\MarkStudentAttendance;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createClassTeacherTestClass(): Classes
{
    return Classes::create(['name' => 'Class '.str()->random(4), 'order' => 1, 'is_active' => true]);
}

function createClassTeacherTestStudent(Classes $class, int $rollNo): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create()->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function createClassTeacherTestTeacher(string $name): TeacherProfile
{
    return TeacherProfile::create([
        'user_id' => User::factory()->create(['name' => $name, 'user_type' => UserType::Teacher, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);
}

function createClassTeacherAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $admin = User::factory()->create(['user_type' => UserType::Admin]);
    $admin->assignRole('admin');

    return $admin;
}

it('does not notify anyone when the assigned class teacher marks their own class', function () {
    $admin = createClassTeacherAdmin();
    $class = createClassTeacherTestClass();
    $classTeacher = createClassTeacherTestTeacher('Assigned Teacher');
    $class->update(['class_teacher_id' => $classTeacher->id]);
    $student = createClassTeacherTestStudent($class, 1);

    test()->actingAs(grantSuperAdmin($classTeacher->user));

    Livewire::test(MarkStudentAttendance::class)
        ->set('classId', $class->id)
        ->set('date', today()->toDateString())
        ->set('presentIds', [(string) $student->id])
        ->call('save');

    expect($admin->notifications()->count())->toBe(0)
        ->and($classTeacher->user->notifications()->count())->toBe(0);
});

it('notifies admins and the assigned class teacher when a different teacher marks attendance', function () {
    $admin = createClassTeacherAdmin();
    $class = createClassTeacherTestClass();
    $classTeacher = createClassTeacherTestTeacher('Assigned Teacher');
    $class->update(['class_teacher_id' => $classTeacher->id]);
    $otherTeacher = createClassTeacherTestTeacher('Other Teacher');
    $student = createClassTeacherTestStudent($class, 1);

    test()->actingAs(grantSuperAdmin($otherTeacher->user));

    Livewire::test(MarkStudentAttendance::class)
        ->set('classId', $class->id)
        ->set('date', today()->toDateString())
        ->set('presentIds', [(string) $student->id])
        ->call('save');

    expect($admin->notifications()->count())->toBe(1)
        ->and($classTeacher->user->notifications()->count())->toBe(1);

    $notification = $admin->notifications()->first();

    expect($notification->data['title'])->toBe('Attendance Marked by Another Teacher')
        ->and($notification->data['body'])->toContain('Other Teacher')
        ->and($notification->data['body'])->toContain($class->name)
        ->and($notification->data['body'])->toContain('Assigned Teacher');
});

it('does not notify when the class has no assigned class teacher', function () {
    $admin = createClassTeacherAdmin();
    $class = createClassTeacherTestClass();
    $teacher = createClassTeacherTestTeacher('Some Teacher');
    $student = createClassTeacherTestStudent($class, 1);

    test()->actingAs(grantSuperAdmin($teacher->user));

    Livewire::test(MarkStudentAttendance::class)
        ->set('classId', $class->id)
        ->set('date', today()->toDateString())
        ->set('presentIds', [(string) $student->id])
        ->call('save');

    expect($admin->notifications()->count())->toBe(0);
});
