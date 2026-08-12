<?php

use App\Enums\UserType;
use App\Filament\Resources\StudentProfiles\Pages\EditStudentProfile;
use App\Filament\Student\Pages\Auth\EditProfile;
use App\Filament\Student\Pages\Auth\RequestPinPasswordReset;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('redirects a student who must change their password to the profile page', function () {
    $student = makeStudent();

    $response = $this->actingAs($student)->get('/student');

    $response->assertRedirect(Filament\Facades\Filament::getPanel('student')->getProfileUrl());
});

it('does not redirect once the password no longer needs to be changed', function () {
    $student = makeStudent(['must_change_password' => false]);

    $response = $this->actingAs($student)->get('/student');

    $response->assertOk();
});

it('clears must_change_password and stores a hashed pin when the student sets a new password', function () {
    $student = makeStudent();
    $this->actingAs($student);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'password' => 'new-secret-password',
            'passwordConfirmation' => 'new-secret-password',
            'pin' => '1234',
            'pinConfirmation' => '1234',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $student->refresh();

    expect($student->must_change_password)->toBeFalse()
        ->and(Hash::check('1234', $student->pin))->toBeTrue()
        ->and(Hash::check('new-secret-password', $student->password))->toBeTrue();
});

it('lets the admin reset a student password back to default and clears the pin', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $student = makeStudent([
        'must_change_password' => false,
        'pin' => Hash::make('1234'),
    ]);
    $profile = StudentProfile::create([
        'user_id' => $student->id,
        'roll_no' => 1,
        'birth_certificate_no' => 'BC-RESET',
        'session_year' => now()->year,
        'gender' => 'male',
        'status' => 'active',
    ]);

    $this->actingAs($admin);

    Livewire::test(EditStudentProfile::class, ['record' => $profile->id])
        ->callAction('resetPassword');

    $student->refresh();

    expect($student->must_change_password)->toBeTrue()
        ->and($student->pin)->toBeNull()
        ->and(Hash::check('password', $student->password))->toBeTrue();
});

it('resets the password via a correct email and pin', function () {
    $student = makeStudent([
        'must_change_password' => false,
        'pin' => Hash::make('4321'),
    ]);

    Livewire::test(RequestPinPasswordReset::class)
        ->fillForm([
            'email' => $student->email,
            'pin' => '4321',
            'password' => 'brand-new-password',
            'passwordConfirmation' => 'brand-new-password',
        ])
        ->call('resetWithPin');

    expect(Hash::check('brand-new-password', $student->fresh()->password))->toBeTrue();
});

it('rejects an incorrect pin without changing the password', function () {
    $student = makeStudent([
        'must_change_password' => false,
        'pin' => Hash::make('4321'),
    ]);

    Livewire::test(RequestPinPasswordReset::class)
        ->fillForm([
            'email' => $student->email,
            'pin' => '0000',
            'password' => 'brand-new-password',
            'passwordConfirmation' => 'brand-new-password',
        ])
        ->call('resetWithPin');

    expect(Hash::check('brand-new-password', $student->fresh()->password))->toBeFalse();
});

it('locks pin attempts out after 5 failures for the same email', function () {
    $student = makeStudent([
        'must_change_password' => false,
        'pin' => Hash::make('4321'),
    ]);

    RateLimiter::clear('student-pin-reset:'.sha1($student->email));

    for ($i = 0; $i < 5; $i++) {
        Livewire::test(RequestPinPasswordReset::class)
            ->fillForm([
                'email' => $student->email,
                'pin' => '0000',
                'password' => 'brand-new-password',
                'passwordConfirmation' => 'brand-new-password',
            ])
            ->call('resetWithPin');
    }

    expect(RateLimiter::tooManyAttempts('student-pin-reset:'.sha1($student->email), 5))->toBeTrue();

    Livewire::test(RequestPinPasswordReset::class)
        ->fillForm([
            'email' => $student->email,
            'pin' => '4321',
            'password' => 'brand-new-password',
            'passwordConfirmation' => 'brand-new-password',
        ])
        ->call('resetWithPin');

    expect(Hash::check('brand-new-password', $student->fresh()->password))->toBeFalse();
});
