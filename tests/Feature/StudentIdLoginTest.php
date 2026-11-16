<?php

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Filament\Student\Pages\Auth\Login;
use App\Models\Classes;
use App\Models\SchoolSetting;
use App\Models\StudentProfile;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('student'));
});

function makeStudentWithProfile(array $userAttributes = []): StudentProfile
{
    $user = makeStudent(['must_change_password' => false, ...$userAttributes]);

    return $user->studentProfile()->create([
        'roll_no' => 1,
        'current_class_id' => Classes::firstOrCreate(['name' => 'Login Class'], ['order' => 1])->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

it('generates an 8 digit student id from the school code and the profile id', function () {
    SchoolSetting::set(SchoolSetting::SCHOOL_CODE_SETTING, '101');

    $profile = makeStudentWithProfile();

    expect($profile->fresh()->student_id_no)
        ->toBe(sprintf('101%05d', $profile->id))
        ->toHaveLength(8);
});

it('falls back to school code 001 when none is set', function () {
    config(['central.school_id' => null]);

    $profile = makeStudentWithProfile();

    expect($profile->fresh()->student_id_no)->toBe(sprintf('001%05d', $profile->id));
});

it('keeps an existing student id when the school code changes later', function () {
    SchoolSetting::set(SchoolSetting::SCHOOL_CODE_SETTING, '101');
    $profile = makeStudentWithProfile();

    SchoolSetting::set(SchoolSetting::SCHOOL_CODE_SETTING, '202');

    expect($profile->fresh()->student_id_no)->toBe(sprintf('101%05d', $profile->id));
});

it('logs a student in with their student id and password', function () {
    $profile = makeStudentWithProfile();

    Livewire::test(Login::class)
        ->fillForm([
            'student_id' => $profile->student_id_no,
            'password' => 'password',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($profile->user);
});

it('rejects an unknown student id or a wrong password', function (string $field) {
    $profile = makeStudentWithProfile();

    Livewire::test(Login::class)
        ->fillForm([
            'student_id' => $field === 'student_id' ? '99999999' : $profile->student_id_no,
            'password' => $field === 'password' ? 'wrong-password' : 'password',
        ])
        ->call('authenticate')
        ->assertHasFormErrors(['student_id']);

    $this->assertGuest();
})->with(['student_id', 'password']);

it('does not let a student log in with their email any more', function () {
    $profile = makeStudentWithProfile();

    Livewire::test(Login::class)
        ->fillForm([
            'student_id' => $profile->user->email,
            'password' => 'password',
        ])
        ->call('authenticate')
        ->assertHasFormErrors(['student_id']);

    $this->assertGuest();
});

it('blocks an inactive student from logging in', function () {
    $profile = makeStudentWithProfile(['is_active' => false]);

    Livewire::test(Login::class)
        ->fillForm([
            'student_id' => $profile->student_id_no,
            'password' => 'password',
        ])
        ->call('authenticate')
        ->assertHasFormErrors(['student_id']);

    $this->assertGuest();
});
