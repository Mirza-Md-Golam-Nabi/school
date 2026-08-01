<?php

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\Marksheets\Pages\ListMarksheets;
use App\Filament\Resources\Marksheets\Pages\ViewMarksheet;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Marksheet;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function actingAsMarksheetAdmin(): User
{
    return User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
}

it('renders the marksheets list page with its generate header action', function () {
    $admin = actingAsMarksheetAdmin();

    $response = $this->actingAs($admin)->get(ListMarksheets::getUrl());

    $response->assertOk()->assertSee('Generate Marksheets');
});

it('renders the view marksheet page', function () {
    $admin = actingAsMarksheetAdmin();

    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'is_published' => true,
    ]);

    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 5,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $marksheet = Marksheet::create(['student_id' => $student->id, 'exam_id' => $exam->id]);

    $this->actingAs($admin)->get(ViewMarksheet::getUrl(['record' => $marksheet]))->assertOk();
});
