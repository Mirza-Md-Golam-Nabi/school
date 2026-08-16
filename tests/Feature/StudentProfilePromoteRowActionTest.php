<?php

use App\Enums\ExamConfigType;
use App\Enums\Gender;
use App\Enums\PromotionStatus;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\StudentProfiles\Pages\StudentsByClass;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\ExamTypeConfig;
use App\Models\Group;
use App\Models\Section;
use App\Models\StudentClassHistory;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createRowPromotionTestStudent(Classes $class, int $rollNo): StudentProfile
{
    $user = User::factory()->create([
        'email' => sprintf('std%05d@school.com', random_int(10000, 99999)),
        'user_type' => UserType::Student,
    ]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

it('promotes a single student from the table row action without changing their login email', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $fromClass = Classes::create(['name' => 'Row Promote Class 5', 'order' => 1]);
    $toClass = Classes::create(['name' => 'Row Promote Class 6', 'order' => 2]);
    $toSection = Section::create(['class_id' => $toClass->id, 'name' => 'A']);

    $student = createRowPromotionTestStudent($fromClass, rollNo: 4);
    $originalEmail = $student->user->email;

    test()->actingAs($admin);

    Livewire::test(StudentsByClass::class, ['classId' => $fromClass->id])
        ->callAction(TestAction::make('promote')->table($student), [
            'status' => 'promoted',
            'class_id' => $toClass->id,
            'section_id' => $toSection->id,
            'group_id' => null,
            'roll_no' => 2,
            'remarks' => 'ভালো ফলাফল',
        ])
        ->assertHasNoActionErrors();

    $student->refresh();

    expect($student->current_class_id)->toBe($toClass->id)
        ->and($student->current_section_id)->toBe($toSection->id)
        ->and($student->roll_no)->toBe(2)
        ->and($student->session_year)->toBe(now()->year + 1)
        ->and($student->user->fresh()->email)->toBe($originalEmail);

    $history = StudentClassHistory::where('student_id', $student->id)->sole();

    expect($history->class_id)->toBe($fromClass->id)
        ->and($history->remarks)->toBe('ভালো ফলাফল');
});

it('requires a target class and new roll before promoting or repeating a student', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Row Promote Class Validation', 'order' => 1]);
    $student = createRowPromotionTestStudent($class, rollNo: 1);

    test()->actingAs($admin);

    Livewire::test(StudentsByClass::class, ['classId' => $class->id])
        ->callAction(TestAction::make('promote')->table($student), [
            'status' => 'promoted',
            'class_id' => null,
            'section_id' => null,
            'group_id' => null,
            'roll_no' => null,
            'remarks' => null,
        ])
        ->assertHasActionErrors(['class_id' => 'required', 'roll_no' => 'required']);
});

it('defaults to Graduated with no target class when the student is in the terminal class', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Row Promote Class 10', 'order' => 10]);
    $student = createRowPromotionTestStudent($class, rollNo: 3);

    test()->actingAs($admin);

    Livewire::test(StudentsByClass::class, ['classId' => $class->id])
        ->mountAction(TestAction::make('promote')->table($student))
        ->assertSchemaStateSet([
            'status' => PromotionStatus::Graduated,
            'class_id' => null,
            'roll_no' => null,
        ]);
});

it('graduates a student from the terminal class without requiring a target class or new roll', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Row Promote Class 10 Graduate', 'order' => 10]);
    $student = createRowPromotionTestStudent($class, rollNo: 3);
    $originalEmail = $student->user->email;

    test()->actingAs($admin);

    Livewire::test(StudentsByClass::class, ['classId' => $class->id])
        ->callAction(TestAction::make('promote')->table($student), [
            'status' => 'graduated',
            'class_id' => null,
            'section_id' => null,
            'group_id' => null,
            'roll_no' => null,
            'remarks' => 'SSC সম্পন্ন',
        ])
        ->assertHasNoActionErrors();

    $student->refresh();

    expect($student->status)->toBe(StudentStatus::Graduated)
        ->and($student->current_class_id)->toBe($class->id)
        ->and($student->roll_no)->toBe(3)
        ->and($student->user->fresh()->email)->toBe($originalEmail);

    $history = StudentClassHistory::where('student_id', $student->id)->sole();

    expect($history->status->value)->toBe('graduated')
        ->and($history->remarks)->toBe('SSC সম্পন্ন');
});

it('shows the final main exam merit rank as read-only info when graduating a student', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Row Promote Class 10 Merit', 'order' => 10]);
    $student = createRowPromotionTestStudent($class, rollNo: 3);

    $examType = ExamType::create(['name' => 'Final Exam', 'is_active' => true]);
    ExamTypeConfig::create(['exam_type_id' => $examType->id, 'type' => ExamConfigType::Main]);
    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
    ]);
    StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'class_rank' => 2,
    ]);

    test()->actingAs($admin);

    Livewire::test(StudentsByClass::class, ['classId' => $class->id])
        ->mountAction(TestAction::make('promote')->table($student))
        ->assertSchemaComponentVisible('final_merit_rank');
});

it('hides the final merit rank info for a normal promotion with a next class', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $fromClass = Classes::create(['name' => 'Row Promote Class 5 Merit', 'order' => 1]);
    Classes::create(['name' => 'Row Promote Class 6 Merit', 'order' => 2]);
    $student = createRowPromotionTestStudent($fromClass, rollNo: 3);

    test()->actingAs($admin);

    Livewire::test(StudentsByClass::class, ['classId' => $fromClass->id])
        ->mountAction(TestAction::make('promote')->table($student))
        ->assertSchemaComponentHidden('final_merit_rank');
});

it("defaults the group to the student's current group when the target class offers it", function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $fromClass = Classes::create(['name' => 'Row Promote Class 9 Group', 'order' => 9, 'has_group' => true]);
    $toClass = Classes::create(['name' => 'Row Promote Class 10 Group', 'order' => 10, 'has_group' => true]);
    $group = Group::create(['name' => 'Science', 'is_active' => true]);
    $fromClass->groups()->attach($group->id);
    $toClass->groups()->attach($group->id);

    $student = createRowPromotionTestStudent($fromClass, rollNo: 5);
    $student->update(['current_group_id' => $group->id]);

    test()->actingAs($admin);

    Livewire::test(StudentsByClass::class, ['classId' => $fromClass->id])
        ->mountAction(TestAction::make('promote')->table($student))
        ->assertSchemaStateSet([
            'class_id' => $toClass->id,
            'group_id' => $group->id,
        ]);
});

it("leaves the group blank when the target class does not offer the student's current group", function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $fromClass = Classes::create(['name' => 'Row Promote Class 9 No Group', 'order' => 9, 'has_group' => true]);
    $toClass = Classes::create(['name' => 'Row Promote Class 10 No Group', 'order' => 10, 'has_group' => true]);
    $group = Group::create(['name' => 'Science', 'is_active' => true]);
    $fromClass->groups()->attach($group->id);

    $student = createRowPromotionTestStudent($fromClass, rollNo: 5);
    $student->update(['current_group_id' => $group->id]);

    test()->actingAs($admin);

    Livewire::test(StudentsByClass::class, ['classId' => $fromClass->id])
        ->mountAction(TestAction::make('promote')->table($student))
        ->assertSchemaStateSet([
            'class_id' => $toClass->id,
            'group_id' => null,
        ]);
});

it('hides the promote action for a student who is no longer active', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Row Promote Class Inactive', 'order' => 1]);

    $student = createRowPromotionTestStudent($class, rollNo: 1);
    $student->update(['status' => StudentStatus::Dropped]);

    test()->actingAs($admin);

    Livewire::test(StudentsByClass::class, ['classId' => $class->id])
        ->assertActionHidden(TestAction::make('promote')->table($student));
});
