<?php

use App\Actions\FindStudentByScannedCardAction;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\FeePayments\Pages\CreateFeePayment;
use App\Filament\Teacher\Resources\FeePayments\Pages\CreateFeePayment as TeacherCreateFeePayment;
use App\Models\Classes;
use App\Models\Permission;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeScanTestStudent(?Classes $class = null, StudentStatus $status = StudentStatus::Active): StudentProfile
{
    $class ??= Classes::create(['name' => 'Class '.str()->random(4), 'order' => 1, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => User::factory()->create(['name' => 'Scan Student '.str()->random(4), 'user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 5,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => $status,
    ])->fresh();
}

function makeScanTestTeacher(): TeacherProfile
{
    $teacher = TeacherProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    foreach (['view_fee_payments', 'create_fee_payments'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $teacher->user->assignRole('teacher');
    $teacher->user->givePermissionTo(['view_fee_payments', 'create_fee_payments']);

    return $teacher;
}

it('finds the student from the scanned QR link, the bare token or the typed Student ID', function (callable $scanned) {
    $student = makeScanTestStudent();

    expect(app(FindStudentByScannedCardAction::class)->handle($scanned($student))?->id)->toBe($student->id);
})->with([
    'QR link' => [fn (StudentProfile $student): string => $student->idCardVerificationUrl()],
    'QR link with spaces' => [fn (StudentProfile $student): string => '  '.$student->idCardVerificationUrl().' '],
    'bare token' => [fn (StudentProfile $student): string => $student->id_card_token],
    'Student ID' => [fn (StudentProfile $student): string => $student->idCardNumber()],
]);

it('finds nothing for a reissued card, a deleted student or junk', function () {
    $student = makeScanTestStudent();
    $oldLink = $student->idCardVerificationUrl();
    $student->reissueIdCardToken();

    $deleted = makeScanTestStudent();
    $deletedLink = $deleted->idCardVerificationUrl();
    $deleted->delete();

    $find = app(FindStudentByScannedCardAction::class);

    expect($find->handle($oldLink))->toBeNull()
        ->and($find->handle($deletedLink))->toBeNull()
        ->and($find->handle('hello world'))->toBeNull()
        ->and($find->handle(''))->toBeNull();
});

it('picks the student, class and student type on the admin fee form when a card is scanned', function () {
    test()->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));
    $student = makeScanTestStudent();

    Livewire::test(CreateFeePayment::class)
        ->fillForm(['scanned_id_card' => $student->idCardVerificationUrl()])
        ->assertSchemaStateSet([
            'scanned_id_card' => null,
            'class_id_filter' => $student->current_class_id,
            'student_type' => 'current',
            'student_id' => $student->id,
        ])
        ->assertNotified($student->user->name.' (Roll: 5)');
});

it('switches the admin form to former students for a transferred student\'s card', function () {
    test()->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));
    $student = makeScanTestStudent(status: StudentStatus::Transferred);

    Livewire::test(CreateFeePayment::class)
        ->fillForm(['scanned_id_card' => $student->idCardNumber()])
        ->assertSchemaStateSet(['student_type' => 'former', 'student_id' => $student->id]);
});

it('warns and picks nobody when the scanned card is not on record', function () {
    test()->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    Livewire::test(CreateFeePayment::class)
        ->fillForm(['scanned_id_card' => '99999999'])
        ->assertSchemaStateSet(['student_id' => null])
        ->assertNotified('শিক্ষার্থী পাওয়া যায়নি');
});

it('lets a class teacher scan their own student but not another class\'s', function () {
    $teacher = makeScanTestTeacher();
    $ownClass = Classes::create(['name' => 'Own Class', 'order' => 1, 'is_active' => true, 'class_teacher_id' => $teacher->id]);
    $ownStudent = makeScanTestStudent($ownClass);
    $otherStudent = makeScanTestStudent();

    test()->actingAs($teacher->user);

    Livewire::test(TeacherCreateFeePayment::class)
        ->fillForm(['scanned_id_card' => $ownStudent->idCardVerificationUrl()])
        ->assertSchemaStateSet(['student_id' => $ownStudent->id]);

    Livewire::test(TeacherCreateFeePayment::class)
        ->fillForm(['scanned_id_card' => $otherStudent->idCardVerificationUrl()])
        ->assertSchemaStateSet(['student_id' => null])
        ->assertNotified('এই শিক্ষার্থীর ফি এখানে নেওয়া যাবে না');
});

it('offers the camera scan button on the create form, wired to the scan box', function () {
    test()->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    Livewire::test(CreateFeePayment::class)
        ->assertSee('ক্যামেরা দিয়ে স্ক্যান')
        ->assertSee('id-card-camera-scanner', false)
        ->assertSee('data.scanned_id_card', false);
});

it('picks the student when the camera hands the scan to the box the way the browser does', function () {
    test()->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));
    $student = makeScanTestStudent();

    Livewire::test(CreateFeePayment::class)
        ->set('data.scanned_id_card', $student->idCardVerificationUrl())
        ->assertSet('data.student_id', $student->id)
        ->assertSet('data.scanned_id_card', null);
});
