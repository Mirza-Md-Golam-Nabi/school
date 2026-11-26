<?php

use App\Actions\FindDuplicateRollsAction;
use App\Enums\ExamConfigType;
use App\Enums\Gender;
use App\Enums\PromotionRollPolicy;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Pages\BulkPromoteStudentsForClass;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\ExamTypeConfig;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeRollPolicyStudent(Classes $class, int $rollNo, int $sessionYear, string $name = 'Student'): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create(['name' => $name, 'user_type' => UserType::Student])->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'session_year' => $sessionYear,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function rankRollPolicyStudentInMainExam(Classes $class, StudentProfile $student, int $sessionYear, int $rank): void
{
    $examType = ExamType::create(['name' => 'Final Exam', 'is_active' => true]);
    ExamTypeConfig::create(['exam_type_id' => $examType->id, 'type' => ExamConfigType::Main]);

    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => $sessionYear,
        'start_date' => "{$sessionYear}-01-01",
        'end_date' => "{$sessionYear}-01-10",
    ]);

    StudentMeritRanking::create(['exam_id' => $exam->id, 'student_id' => $student->id, 'class_id' => $class->id, 'class_rank' => $rank]);
}

it('defaults a class to filling the new roll from the merit rank', function () {
    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);

    expect($class->fresh()->promotion_roll_policy)->toBe(PromotionRollPolicy::Merit)
        ->and($class->fresh()->keepsRollOnPromotion())->toBeFalse();
});

it('pre-fills the merit rank as the new roll by default', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin])));

    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);
    Classes::create(['name' => 'Class 6', 'order' => 6]);
    $student = makeRollPolicyStudent($class, rollNo: 9, sessionYear: 2026);
    rankRollPolicyStudentInMainExam($class, $student, 2026, rank: 2);

    Livewire::test(BulkPromoteStudentsForClass::class, ['classId' => $class->id, 'year' => 2026])
        ->assertSet("promotions.{$student->id}.roll_no", 2)
        ->assertSet('keepsCurrentRoll', false);
});

it('pre-fills the current roll when the class keeps rolls on promotion', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin])));

    $firstYear = Classes::create(['name' => 'Class 11', 'order' => 11, 'promotion_roll_policy' => PromotionRollPolicy::KeepCurrent]);
    Classes::create(['name' => 'Class 12', 'order' => 12]);
    $student = makeRollPolicyStudent($firstYear, rollNo: 9, sessionYear: 2026);
    rankRollPolicyStudentInMainExam($firstYear, $student, 2026, rank: 2);

    Livewire::test(BulkPromoteStudentsForClass::class, ['classId' => $firstYear->id, 'year' => 2026])
        ->assertSet("promotions.{$student->id}.roll_no", 9)
        ->assertSet('keepsCurrentRoll', true)
        ->assertSee('আগের রোলই থাকে');
});

it('promotes a student onto a roll already held in the target class, warning instead of blocking', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin])));

    $firstYear = Classes::create(['name' => 'Class 11', 'order' => 11, 'promotion_roll_policy' => PromotionRollPolicy::KeepCurrent]);
    $secondYear = Classes::create(['name' => 'Class 12', 'order' => 12]);
    $promoted = makeRollPolicyStudent($firstYear, rollNo: 15, sessionYear: 2026, name: 'Rahim');
    makeRollPolicyStudent($secondYear, rollNo: 15, sessionYear: 2027, name: 'Karim');

    Livewire::test(BulkPromoteStudentsForClass::class, ['classId' => $firstYear->id, 'year' => 2026])
        ->call('promote')
        ->assertNotified('একই রোল একাধিক student-এর');

    expect($promoted->fresh())
        ->current_class_id->toBe($secondYear->id)
        ->roll_no->toBe(15)
        ->session_year->toBe(2027);
});

it('lists the students who share a roll in the same class, section and session', function () {
    $class = Classes::create(['name' => 'Class 12', 'order' => 12]);
    $rahim = makeRollPolicyStudent($class, rollNo: 15, sessionYear: 2027, name: 'Rahim');
    makeRollPolicyStudent($class, rollNo: 15, sessionYear: 2027, name: 'Karim');
    makeRollPolicyStudent($class, rollNo: 15, sessionYear: 2026, name: 'Older Session');
    $unique = makeRollPolicyStudent($class, rollNo: 16, sessionYear: 2027, name: 'Salam');

    $duplicates = app(FindDuplicateRollsAction::class);

    expect($duplicates->handle([$rahim->id])->all())->toBe(['Class 12 — Roll 15: Rahim, Karim'])
        ->and($duplicates->handle([$unique->id]))->toBeEmpty();
});
