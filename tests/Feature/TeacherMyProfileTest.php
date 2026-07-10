<?php

use App\Enums\AddressType;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\UserType;
use App\Filament\Teacher\Pages\MyProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the logged-in teacher their own basic and professional info across tabs', function () {
    $user = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true, 'name' => 'Jasim Uddin']);

    $teacher = TeacherProfile::create([
        'user_id' => $user->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
        'designation' => 'Senior Teacher',
        'department' => 'Science',
        'qualification' => 'M.Sc in Physics',
    ]);

    $teacher->addresses()->create([
        'type' => AddressType::Present,
        'address' => 'House 5, Road 2, Chittagong',
        'is_same' => true,
    ]);

    $response = $this->actingAs($user)->get(MyProfile::getUrl(panel: 'teacher'));

    $response->assertOk()
        ->assertSee('Jasim Uddin')
        ->assertSee('Senior Teacher')
        ->assertSee('Science')
        ->assertSee('M.Sc in Physics')
        ->assertSee('House 5, Road 2, Chittagong');
});

it('does not error when a teacher has no profile yet', function () {
    $user = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);

    $response = $this->actingAs($user)->get(MyProfile::getUrl(panel: 'teacher'));

    $response->assertOk()->assertSee('No profile information found.');
});

it('links the dashboard widget to the profile page with name and designation', function () {
    $user = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true, 'name' => 'Farida Yasmin']);

    TeacherProfile::create([
        'user_id' => $user->id,
        'gender' => Gender::Female,
        'status' => EmploymentStatus::Active,
        'designation' => 'Assistant Teacher',
        'department' => 'English',
    ]);

    $data = $this->actingAs($user)
        ->get(route('filament.teacher.pages.dashboard'))
        ->assertOk();

    $data->assertSee('Farida Yasmin')
        ->assertSee('Assistant Teacher')
        ->assertSee('href="'.MyProfile::getUrl(panel: 'teacher').'"', false);
});
