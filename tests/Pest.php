<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\ExamType;
use App\Models\Permission;
use App\Models\ResultRule;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Bypasses all fine-grained permission checks via the app's Gate::before
 * super-admin bypass, for tests that exercise feature behavior rather than
 * authorization itself.
 */
function grantSuperAdmin(User $user): User
{
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $user->assignRole('super-admin');

    return $user;
}

/**
 * Creates an active student user with the "student" role, for tests that
 * exercise student-panel access, password/PIN, or profile behavior.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeStudent(array $attributes = []): User
{
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    $user = User::factory()->create(array_merge([
        'user_type' => UserType::Student,
        'is_active' => true,
        'must_change_password' => true,
    ], $attributes));

    $user->assignRole('student');

    return $user;
}

/**
 * A result rule for the class: its target exam type and the components, each
 * [exam type, share %, must pass?] — e.g. [[$classTest, 20], [$halfYearly, 80]].
 *
 * @param  list<array{0: ExamType, 1: float|int, 2?: bool}>  $components
 * @param  array<string, mixed>  $attributes
 */
function makeResultRule(Classes $class, ExamType $target, array $components, array $attributes = []): ResultRule
{
    $rule = ResultRule::factory()->create(['target_exam_type_id' => $target->id, 'full_marks' => 100, ...$attributes]);
    $rule->classes()->attach($class);

    foreach ($components as $order => $component) {
        $rule->components()->create([
            'exam_type_id' => $component[0]->id,
            'weight_percent' => $component[1],
            'must_pass' => $component[2] ?? false,
            'sort_order' => $order,
        ]);
    }

    return $rule;
}

/**
 * An active student, optionally in a class — shared by the notice tests.
 */
function createNoticeTestStudent(?Classes $class = null): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 1,
        'current_class_id' => $class?->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

/**
 * An active teacher without any role — shared by the notice tests.
 */
function createNoticeTestTeacher(): TeacherProfile
{
    $user = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);

    return TeacherProfile::create([
        'user_id' => $user->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);
}

/**
 * An active teacher holding the student fee invoice permissions (plus any
 * extra ones) — shared by the fee invoice tests.
 *
 * @param  array<int, string>  $extraPermissions
 */
function createInvoiceTestTeacher(string $name, array $extraPermissions = []): TeacherProfile
{
    $teacher = TeacherProfile::create([
        'user_id' => User::factory()->create(['name' => $name, 'user_type' => UserType::Teacher, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

    $permissions = array_unique([...['view_student_fee_invoices', 'create_student_fee_invoices', 'edit_student_fee_invoices', 'delete_student_fee_invoices'], ...$extraPermissions]);

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $teacher->user->assignRole('teacher');
    $teacher->user->givePermissionTo($permissions);

    return $teacher;
}

/**
 * An active class, optionally with a class teacher — shared by the fee invoice tests.
 */
function createInvoiceTestClass(?TeacherProfile $classTeacher = null): Classes
{
    return Classes::create([
        'name' => 'Class '.str()->random(4),
        'order' => 1,
        'is_active' => true,
        'class_teacher_id' => $classTeacher?->id,
    ]);
}

/**
 * An active student on the given roll in the class — shared by the fee invoice tests.
 */
function createInvoiceTestStudent(Classes $class, int $rollNo): StudentProfile
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

/**
 * Runs the callback and returns every single-row primary key lookup it made
 * on the given tables: the shape of a lazy-loaded belongsTo / morphTo.
 *
 * @param  list<string>  $tables
 * @return list<string>
 */
function singleRowLookupsDuring(array $tables, Closure $callback): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $callback();
    } finally {
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
    }

    $tablePattern = implode('|', array_map(fn (string $table): string => preg_quote($table, '/'), $tables));
    $lookupPattern = '/from "('.$tablePattern.')" where "\1"\."id" = \?( and "\1"\."deleted_at" is null)? limit 1/';

    return collect($queries)
        ->pluck('query')
        ->filter(fn (string $query): bool => (bool) preg_match($lookupPattern, $query))
        ->values()
        ->all();
}
