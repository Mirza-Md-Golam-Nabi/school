<?php

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\Marksheets\MarksheetResource;
use App\Filament\Resources\Marksheets\Pages\ListMarksheets;
use App\Filament\Resources\Marksheets\Pages\ManageClassMarksheets;
use App\Filament\Resources\Marksheets\Pages\ViewMarksheet;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Marksheet;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function actingAsMarksheetAdmin(): User
{
    return grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
}

it('renders the marksheets list page as class cards with its generate header action', function () {
    $admin = actingAsMarksheetAdmin();
    Classes::create(['name' => 'Class One', 'order' => 1, 'is_active' => true]);

    $response = $this->actingAs($admin)->get(ListMarksheets::getUrl());

    $response->assertOk()
        ->assertSee('Generate Marksheets')
        ->assertSee('Class One');
});

it('renders an empty state on the list page when there are no active classes', function () {
    $admin = actingAsMarksheetAdmin();

    $response = $this->actingAs($admin)->get(ListMarksheets::getUrl());

    $response->assertOk()->assertSee('No active classes found');
});

it('renders the class-scoped marksheets page, scoped to that class only, with its own generate action', function () {
    $admin = actingAsMarksheetAdmin();

    $classA = Classes::create(['name' => 'Class Scoped A', 'order' => 1, 'is_active' => true]);
    $classB = Classes::create(['name' => 'Class Scoped B', 'order' => 2, 'is_active' => true]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    $examA = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $classA->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'is_published' => true,
    ]);
    $examB = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $classB->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'is_published' => true,
    ]);

    $studentA = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 1,
        'current_class_id' => $classA->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
    $studentB = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 1,
        'current_class_id' => $classB->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    Marksheet::create(['student_id' => $studentA->id, 'exam_id' => $examA->id]);
    Marksheet::create(['student_id' => $studentB->id, 'exam_id' => $examB->id]);

    $response = $this->actingAs($admin)->get(
        MarksheetResource::getUrl('class-marksheets', ['class' => $classA->id])
    );

    $response->assertOk()->assertSee('Generate Marksheets');

    $component = Livewire::test(ManageClassMarksheets::class, ['classId' => $classA->id]);

    $component->assertCanSeeTableRecords(Marksheet::where('student_id', $studentA->id)->get())
        ->assertCanNotSeeTableRecords(Marksheet::where('student_id', $studentB->id)->get());
});

it('sorts marksheets by student roll number by default', function () {
    $admin = actingAsMarksheetAdmin();

    $class = Classes::create(['name' => 'Class One', 'order' => 1, 'is_active' => true]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'is_published' => true,
    ]);

    $makeStudent = fn (int $rollNo) => StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    // Created out of roll order, to prove the default sort isn't just insertion order.
    $marksheetRoll3 = Marksheet::create(['student_id' => $makeStudent(3)->id, 'exam_id' => $exam->id]);
    $marksheetRoll1 = Marksheet::create(['student_id' => $makeStudent(1)->id, 'exam_id' => $exam->id]);
    $marksheetRoll2 = Marksheet::create(['student_id' => $makeStudent(2)->id, 'exam_id' => $exam->id]);

    $this->actingAs($admin);

    Livewire::test(ManageClassMarksheets::class, ['classId' => $class->id])
        ->assertCanSeeTableRecords([$marksheetRoll1, $marksheetRoll2, $marksheetRoll3], inOrder: true);
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
