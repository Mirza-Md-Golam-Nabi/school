<?php

use App\Actions\BuildClassAttendanceReportPdfAction;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Pages\StudentAttendanceReport;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

it('builds a valid attendance report pdf for a class with active students', function () {
    $class = Classes::create(['name' => 'Report Class', 'order' => 1]);
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $pdf = app(BuildClassAttendanceReportPdfAction::class)->handle($class, 2026, 3);

    expect($pdf)->toStartWith('%PDF');
});

it('streams the attendance report pdf as a download with a class-name and month-name filename', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Class Six', 'order' => 1]);
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $response = $this->actingAs($admin)->get(route('attendance-report.class.download', [
        'class' => $class->id,
        'year' => 2026,
        'month' => 7,
    ]));

    $response->assertStatus(Response::HTTP_OK);
    $response->assertHeader('Content-Type', 'application/pdf');

    $disposition = $response->headers->get('Content-Disposition');

    expect($disposition)->toContain('attendance-report-Class-Six-July-2026.pdf')
        ->not->toContain($class->id.'-2026-07');
});

it('defaults the report form to the current session year and dispatches one download url per selected class', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $classOne = Classes::create(['name' => 'Report Page Class One', 'order' => 1, 'is_active' => true]);
    $classTwo = Classes::create(['name' => 'Report Page Class Two', 'order' => 2, 'is_active' => true]);

    Livewire::actingAs($admin);

    Livewire::test(StudentAttendanceReport::class)
        ->assertSchemaStateSet(['session_year' => now()->year])
        ->fillForm(['class_ids' => [$classOne->id, $classTwo->id], 'month' => 3, 'session_year' => 2026])
        ->callAction('download')
        ->assertDispatched('download-attendance-reports', function (string $name, array $params) use ($classOne, $classTwo) {
            $expected = [
                route('attendance-report.class.download', ['class' => $classOne->id, 'year' => 2026, 'month' => 3]),
                route('attendance-report.class.download', ['class' => $classTwo->id, 'year' => 2026, 'month' => 3]),
            ];

            return $params['urls'] === $expected;
        });
});
