<?php

use App\Actions\Attendance\SaveClassAttendanceAction;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Pages\StudentAttendance;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Regression test: the dashboard's "today" counts used `where('date', today())`,
 * binding a Carbon object. Since attendance rows are written with a plain
 * "Y-m-d" string, the Carbon object's implicit full-datetime string never
 * matched, so today's attendance always looked unmarked with zero counts.
 */
it('reflects attendance marked today on the class attendance dashboard', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]);

    $presentStudent = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $absentStudent = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 2,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    app(SaveClassAttendanceAction::class)->handle(
        classId: $class->id,
        class: $class,
        date: now()->toDateString(),
        students: collect([$presentStudent, $absentStudent]),
        presentIds: [(string) $presentStudent->id],
        markedBy: $admin->id,
        markedByName: $admin->name,
    );

    $data = (new StudentAttendance)->getViewData();

    $classRow = $data['classes']->firstWhere('id', $class->id);

    expect($classRow->is_marked)->toBeTrue()
        ->and($classRow->present_today)->toBe(1)
        ->and($classRow->absent_today)->toBe(1);

    expect($data['presentToday'])->toBe(1)
        ->and($data['absentToday'])->toBe(1)
        ->and($data['notMarkedToday'])->toBe(0);
});
