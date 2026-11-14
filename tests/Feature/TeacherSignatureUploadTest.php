<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Filament\Resources\TeacherProfiles\Pages\CreateTeacherProfile;
use App\Filament\Resources\TeacherProfiles\Pages\EditTeacherProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    $this->actingAs(grantSuperAdmin(User::factory()->create()));
});

it('stores the uploaded signature when creating a teacher', function () {
    Livewire::test(CreateTeacherProfile::class)
        ->fillForm([
            'email' => 'signature.teacher@example.com',
            'name' => 'Signature Teacher',
            'password' => 'password',
            'status' => EmploymentStatus::Active->value,
            'gender' => Gender::Male->value,
            'nationality' => 'Bangladeshi',
            'present_address' => 'Dhaka',
            'signature' => UploadedFile::fake()->image('signature.png', 300, 100),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $profile = User::where('email', 'signature.teacher@example.com')->first()->teacherProfile;

    expect($profile->signature)->toStartWith('teacher-profiles/signatures/');
    Storage::disk('public')->assertExists($profile->signature);
});

it('replaces the signature when editing a teacher', function () {
    $profile = TeacherProfile::factory()->create();

    Livewire::test(EditTeacherProfile::class, ['record' => $profile->getRouteKey()])
        ->fillForm([
            'present_address' => 'Dhaka',
            'signature' => UploadedFile::fake()->image('new-signature.png', 300, 100),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($profile->refresh()->signature)->toStartWith('teacher-profiles/signatures/');
    Storage::disk('public')->assertExists($profile->signature);
});

it('rejects a non-image signature file', function () {
    Livewire::test(CreateTeacherProfile::class)
        ->fillForm([
            'email' => 'bad.signature@example.com',
            'name' => 'Bad Signature',
            'signature' => UploadedFile::fake()->create('signature.pdf', 10, 'application/pdf'),
        ])
        ->call('create')
        ->assertHasFormErrors(['signature']);
});
