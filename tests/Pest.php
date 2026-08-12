<?php

use App\Enums\UserType;
use App\Models\User;
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
