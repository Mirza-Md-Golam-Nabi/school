<?php

use App\Enums\ClassLevel;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Permission;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['view_admit_cards', 'view_marksheets', 'view_student_profiles', 'view_teacher_profiles', 'view_staff_profiles', 'view_academic', 'view_attendance', 'view_fee_payments'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $this->class = Classes::create(['name' => 'Class 6', 'level' => ClassLevel::Secondary, 'order' => 6, 'has_group' => false]);
    $this->exam = Exam::create([
        'exam_type_id' => ExamType::create(['name' => 'Annual'])->id,
        'class_id' => $this->class->id,
        'session_year' => now()->year,
        'start_date' => now(),
        'end_date' => now()->addDays(5),
        'is_published' => true,
    ]);
});

function pdfRouteUser(UserType $type, string ...$permissions): User
{
    $user = User::factory()->create(['user_type' => $type, 'is_active' => true]);

    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

it('blocks a student from school-wide PDF downloads', function (string $url) {
    test()->actingAs(pdfRouteUser(UserType::Student));

    test()->get($url)->assertForbidden();
})->with([
    'class student list' => fn () => route('student-list.class.download', ['class' => test()->class]),
    'class student id cards' => fn () => route('student-id-cards.class.download', ['class' => test()->class]),
    'class seat plan' => fn () => route('seat-plan.class.download', ['class' => test()->class]),
    'class admit cards' => fn () => route('admit-cards.class.download', ['class' => test()->class, 'exam' => test()->exam]),
    'class marksheets' => fn () => route('marksheets.class.download', ['class' => test()->class, 'exam' => test()->exam]),
    'class attendance report' => fn () => route('attendance-report.class.download', ['class' => test()->class, 'year' => 2026, 'month' => 1]),
    'employee attendance report' => fn () => route('attendance-report.employees.download', ['year' => 2026, 'month' => 1]),
    'teacher attendance sheet' => fn () => route('attendance-report.teachers.download', ['year' => 2026, 'month' => 1]),
    'exam schedule' => fn () => route('exams.schedule.download', ['exam' => test()->exam]),
    'tabulation sheet' => fn () => route('exams.tabulation-sheet.download', ['exam' => test()->exam]),
    'result report' => fn () => route('exams.result-report.download', ['exam' => test()->exam]),
    'subject list' => fn () => route('subject-list.download'),
    'teacher list' => fn () => route('teacher-list.download'),
    'teacher id cards' => fn () => route('teacher-id-cards.download'),
    'staff id cards' => fn () => route('staff-id-cards.download'),
    'fee payment slip' => fn () => route('fee-payments.slip.download', ['batchId' => 'abc']),
]);

it('blocks a teacher without the permission from another class student list', function () {
    test()->actingAs(pdfRouteUser(UserType::Teacher));

    test()->get(route('student-list.class.download', ['class' => $this->class]))->assertForbidden();
});

it('lets a user with the matching permission past the gate', function () {
    test()->actingAs(pdfRouteUser(UserType::Staff, 'view_student_profiles'));

    expect(test()->get(route('student-list.class.download', ['class' => $this->class]))->status())->not->toBe(403);
});

it('does not let one permission open a different PDF', function () {
    test()->actingAs(pdfRouteUser(UserType::Staff, 'view_student_profiles'));

    test()->get(route('exams.tabulation-sheet.download', ['exam' => $this->exam]))->assertForbidden();
});

it('lets a teacher open their own id card but not a colleague card', function () {
    $teacher = pdfRouteUser(UserType::Teacher);
    $own = TeacherProfile::factory()->create(['user_id' => $teacher->id]);
    $colleague = TeacherProfile::factory()->create(['user_id' => pdfRouteUser(UserType::Teacher)->id]);

    test()->actingAs($teacher);

    test()->get(route('teacher-id-cards.teacher.view', ['teacher' => $colleague]))->assertForbidden();
    test()->get(route('teacher-id-cards.teacher.view', ['teacher' => $own]))->assertSuccessful();
});
