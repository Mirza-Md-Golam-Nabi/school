<?php

use App\Actions\PromoteStudentsAction;
use App\Enums\Gender;
use App\Enums\OptionalSubjectRole;
use App\Enums\PromotionStatus;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Models\Classes;
use App\Models\Group;
use App\Models\Section;
use App\Models\StudentClassHistory;
use App\Models\StudentOptionalSubject;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createStudentForPromotion(Classes $class, int $rollNo, int $sessionYear): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create()->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'session_year' => $sessionYear,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

test('promoting a student snapshots the old class into history and updates the profile', function () {
    $fromClass = Classes::create(['name' => 'Class 5', 'order' => 1]);
    $toClass = Classes::create(['name' => 'Class 6', 'order' => 2]);
    $toSection = Section::create(['class_id' => $toClass->id, 'name' => 'A']);
    $admin = User::factory()->create();

    $student = createStudentForPromotion($fromClass, rollNo: 5, sessionYear: 2026);

    app(PromoteStudentsAction::class)->handle([
        $student->id => [
            'status' => 'promoted',
            'class_id' => $toClass->id,
            'section_id' => $toSection->id,
            'group_id' => null,
            'roll_no' => 3,
            'remarks' => null,
        ],
    ], $admin->id);

    $history = StudentClassHistory::where('student_id', $student->id)->sole();

    expect($history->class_id)->toBe($fromClass->id)
        ->and($history->roll_no)->toBe(5)
        ->and($history->session_year)->toBe(2026)
        ->and($history->status)->toBe(PromotionStatus::Promoted)
        ->and($history->promoted_by)->toBe($admin->id);

    $student->refresh();

    expect($student->current_class_id)->toBe($toClass->id)
        ->and($student->current_section_id)->toBe($toSection->id)
        ->and($student->roll_no)->toBe(3)
        ->and($student->session_year)->toBe(2027)
        ->and($student->status)->toBe(StudentStatus::Active);
});

test('marking a student dropped keeps their current class but updates status', function () {
    $class = Classes::create(['name' => 'Class 5', 'order' => 1]);
    $admin = User::factory()->create();

    $student = createStudentForPromotion($class, rollNo: 7, sessionYear: 2026);

    app(PromoteStudentsAction::class)->handle([
        $student->id => [
            'status' => 'dropped',
            'class_id' => null,
            'section_id' => null,
            'group_id' => null,
            'roll_no' => null,
            'remarks' => 'পারিবারিক কারণে',
        ],
    ], $admin->id);

    $history = StudentClassHistory::where('student_id', $student->id)->sole();

    expect($history->class_id)->toBe($class->id)
        ->and($history->status)->toBe(PromotionStatus::Dropped)
        ->and($history->remarks)->toBe('পারিবারিক কারণে');

    $student->refresh();

    expect($student->current_class_id)->toBe($class->id)
        ->and($student->roll_no)->toBe(7)
        ->and($student->session_year)->toBe(2026)
        ->and($student->status)->toBe(StudentStatus::Dropped);
});

test('graduating a student from the terminal class keeps their class history but marks them graduated', function () {
    $class = Classes::create(['name' => 'Class 10', 'order' => 10]);
    $admin = User::factory()->create();

    $student = createStudentForPromotion($class, rollNo: 2, sessionYear: 2026);

    app(PromoteStudentsAction::class)->handle([
        $student->id => [
            'status' => 'graduated',
            'class_id' => null,
            'section_id' => null,
            'group_id' => null,
            'roll_no' => null,
            'remarks' => 'SSC সম্পন্ন',
        ],
    ], $admin->id);

    $history = StudentClassHistory::where('student_id', $student->id)->sole();

    expect($history->class_id)->toBe($class->id)
        ->and($history->status)->toBe(PromotionStatus::Graduated)
        ->and($history->remarks)->toBe('SSC সম্পন্ন');

    $student->refresh();

    expect($student->current_class_id)->toBe($class->id)
        ->and($student->roll_no)->toBe(2)
        ->and($student->session_year)->toBe(2026)
        ->and($student->status)->toBe(StudentStatus::Graduated);
});

test('promoting into a new group class saves the main/extra optional subject choice for the new class', function () {
    $fromClass = Classes::create(['name' => 'Class 9', 'order' => 9, 'has_group' => true]);
    $toClass = Classes::create(['name' => 'Class 10', 'order' => 10, 'has_group' => true]);
    $group = Group::create(['name' => 'Science', 'is_active' => true]);
    $fromClass->groups()->attach($group->id);
    $toClass->groups()->attach($group->id);

    $biology = Subject::create(['name' => 'Biology', 'has_written' => true, 'is_active' => true]);
    $higherMath = Subject::create(['name' => 'Higher Math', 'has_written' => true, 'is_active' => true]);
    $biology->classes()->attach($toClass->id, ['group_id' => $group->id, 'subject_type' => SubjectType::Optional->value]);
    $higherMath->classes()->attach($toClass->id, ['group_id' => $group->id, 'subject_type' => SubjectType::Optional->value]);

    $admin = User::factory()->create();
    $student = createStudentForPromotion($fromClass, rollNo: 5, sessionYear: 2026);
    $student->update(['current_group_id' => $group->id]);

    // The student's class 9 choice — should stay untouched as history.
    StudentOptionalSubject::create([
        'student_id' => $student->id,
        'class_id' => $fromClass->id,
        'group_id' => $group->id,
        'subject_id' => $biology->id,
        'role' => OptionalSubjectRole::MainOptional,
    ]);

    app(PromoteStudentsAction::class)->handle([
        $student->id => [
            'status' => 'promoted',
            'class_id' => $toClass->id,
            'section_id' => null,
            'group_id' => $group->id,
            'roll_no' => 3,
            'remarks' => null,
            'main_optional_subject_id' => $biology->id,
            'extra_optional_subject_id' => $higherMath->id,
        ],
    ], $admin->id);

    // Old class 9 selection is untouched.
    expect(StudentOptionalSubject::where('student_id', $student->id)->where('class_id', $fromClass->id)->count())->toBe(1);

    expect(StudentOptionalSubject::where('student_id', $student->id)
        ->where('class_id', $toClass->id)
        ->where('role', OptionalSubjectRole::MainOptional)
        ->value('subject_id'))->toBe($biology->id);

    expect(StudentOptionalSubject::where('student_id', $student->id)
        ->where('class_id', $toClass->id)
        ->where('role', OptionalSubjectRole::ExtraOptional)
        ->value('subject_id'))->toBe($higherMath->id);
});

test('a student cannot have two history rows for the same session year', function () {
    $class = Classes::create(['name' => 'Class 5', 'order' => 1]);
    $admin = User::factory()->create();
    $student = createStudentForPromotion($class, rollNo: 1, sessionYear: 2026);

    StudentClassHistory::create([
        'student_id' => $student->id,
        'class_id' => $class->id,
        'roll_no' => 1,
        'session_year' => 2026,
        'status' => 'promoted',
        'promoted_by' => $admin->id,
    ]);

    expect(fn () => StudentClassHistory::create([
        'student_id' => $student->id,
        'class_id' => $class->id,
        'roll_no' => 1,
        'session_year' => 2026,
        'status' => 'repeated',
        'promoted_by' => $admin->id,
    ]))->toThrow(QueryException::class);
});
