<?php

use App\Enums\ExamConfigType;
use App\Enums\Gender;
use App\Enums\OptionalSubjectRole;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Filament\Pages\BulkPromoteStudentsForClass;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\ExamTypeConfig;
use App\Models\Group;
use App\Models\Section;
use App\Models\StudentMeritRanking;
use App\Models\StudentOptionalSubject;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createAdminUser(): User
{
    return grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin]));
}

function createEnrolledStudent(Classes $class, int $rollNo, int $sessionYear): StudentProfile
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

test('new roll defaults to the main exam merit rank, not the old roll number', function () {
    $class = Classes::create(['name' => 'Class 5', 'order' => 1]);
    $student = createEnrolledStudent($class, rollNo: 9, sessionYear: 2026);

    $examType = ExamType::create(['name' => 'Final Exam', 'is_active' => true]);
    ExamTypeConfig::create(['exam_type_id' => $examType->id, 'type' => ExamConfigType::Main]);

    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-10',
    ]);

    StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'class_rank' => 2,
    ]);

    $response = $this->actingAs(createAdminUser())->get(
        BulkPromoteStudentsForClass::getUrl(['classId' => $class->id, 'year' => 2026])
    );

    $response->assertOk();
    $response->assertDontSee('Main Exam-এর merit ranking পাওয়া যায়নি');
});

test('new roll stays blank when the class has no main exam results', function () {
    $class = Classes::create(['name' => 'Class 5', 'order' => 1]);
    createEnrolledStudent($class, rollNo: 9, sessionYear: 2026);

    $response = $this->actingAs(createAdminUser())->get(
        BulkPromoteStudentsForClass::getUrl(['classId' => $class->id, 'year' => 2026])
    );

    $response->assertOk();
    $response->assertSee('Main Exam-এর merit ranking পাওয়া যায়নি');
});

test('the target class group defaults to the student\'s current group when it is offered there', function () {
    $fromClass = Classes::create(['name' => 'Class 9', 'order' => 9, 'has_group' => true]);
    $toClass = Classes::create(['name' => 'Class 10', 'order' => 10, 'has_group' => true]);
    $group = Group::create(['name' => 'Science', 'is_active' => true]);
    $fromClass->groups()->attach($group->id);
    $toClass->groups()->attach($group->id);

    $student = createEnrolledStudent($fromClass, rollNo: 5, sessionYear: now()->year);
    $student->update(['current_group_id' => $group->id]);

    $this->actingAs(createAdminUser());

    Livewire::test(BulkPromoteStudentsForClass::class, ['classId' => $fromClass->id, 'year' => now()->year])
        ->assertSet("promotions.{$student->id}.class_id", $toClass->id)
        ->assertSet("promotions.{$student->id}.group_id", $group->id);
});

test('the target class group stays blank when it does not offer the student\'s current group', function () {
    $fromClass = Classes::create(['name' => 'Class 9', 'order' => 9, 'has_group' => true]);
    $toClass = Classes::create(['name' => 'Class 10', 'order' => 10, 'has_group' => true]);
    $group = Group::create(['name' => 'Science', 'is_active' => true]);
    $fromClass->groups()->attach($group->id);

    $student = createEnrolledStudent($fromClass, rollNo: 5, sessionYear: now()->year);
    $student->update(['current_group_id' => $group->id]);

    $this->actingAs(createAdminUser());

    Livewire::test(BulkPromoteStudentsForClass::class, ['classId' => $fromClass->id, 'year' => now()->year])
        ->assertSet("promotions.{$student->id}.class_id", $toClass->id)
        ->assertSet("promotions.{$student->id}.group_id", null);
});

test('only students from the selected session year are listed when the class holds two cohorts', function () {
    $class = Classes::create(['name' => 'Class 2', 'order' => 2]);
    Classes::create(['name' => 'Class 3', 'order' => 3]);

    $awaitingPromotion = createEnrolledStudent($class, rollNo: 3, sessionYear: 2026);
    $alreadyPromotedIn = createEnrolledStudent($class, rollNo: 1, sessionYear: 2027);

    $this->actingAs(createAdminUser());

    $livewire = Livewire::test(BulkPromoteStudentsForClass::class, ['classId' => $class->id, 'year' => 2026]);

    $livewire->assertSet("promotions.{$awaitingPromotion->id}.status", 'promoted')
        ->assertSet("promotions.{$alreadyPromotedIn->id}", null);

    expect($livewire->instance()->getStudents()->pluck('id')->all())
        ->toBe([$awaitingPromotion->id]);

    $livewireForNextYear = Livewire::test(BulkPromoteStudentsForClass::class, ['classId' => $class->id, 'year' => 2027]);

    expect($livewireForNextYear->instance()->getStudents()->pluck('id')->all())
        ->toBe([$alreadyPromotedIn->id]);
});

test('aborts with 404 when classId or year is missing', function () {
    $this->actingAs(createAdminUser());

    $class = Classes::create(['name' => 'Class 5', 'order' => 1]);

    Livewire::test(BulkPromoteStudentsForClass::class, ['classId' => $class->id, 'year' => 0])
        ->assertNotFound();
});

test('shows the Section and Group selects when the next class offers them', function () {
    $fromClass = Classes::create(['name' => 'Class 9', 'order' => 9, 'has_group' => true]);
    $toClass = Classes::create(['name' => 'Class 10', 'order' => 10, 'has_group' => true]);
    Section::create(['class_id' => $toClass->id, 'name' => 'A']);
    $group = Group::create(['name' => 'Science', 'is_active' => true]);
    $toClass->groups()->attach($group->id);

    $student = createEnrolledStudent($fromClass, rollNo: 1, sessionYear: now()->year);

    $response = $this->actingAs(createAdminUser())->get(
        BulkPromoteStudentsForClass::getUrl(['classId' => $fromClass->id, 'year' => now()->year])
    );

    $response->assertOk()
        ->assertSee("promotions.{$student->id}.section_id", false)
        ->assertSee("promotions.{$student->id}.group_id", false);
});

test('hides the Section select when the next class has no sections', function () {
    $fromClass = Classes::create(['name' => 'Class 9', 'order' => 9]);
    Classes::create(['name' => 'Class 10', 'order' => 10]); // no sections created

    $student = createEnrolledStudent($fromClass, rollNo: 1, sessionYear: now()->year);

    $response = $this->actingAs(createAdminUser())->get(
        BulkPromoteStudentsForClass::getUrl(['classId' => $fromClass->id, 'year' => now()->year])
    );

    $response->assertOk()->assertDontSee("promotions.{$student->id}.section_id", false);
});

test('hides the Group select when the next class does not use groups', function () {
    $fromClass = Classes::create(['name' => 'Class 9', 'order' => 9]);
    Classes::create(['name' => 'Class 10', 'order' => 10, 'has_group' => false]);

    $student = createEnrolledStudent($fromClass, rollNo: 1, sessionYear: now()->year);

    $response = $this->actingAs(createAdminUser())->get(
        BulkPromoteStudentsForClass::getUrl(['classId' => $fromClass->id, 'year' => now()->year])
    );

    $response->assertOk()->assertDontSee("promotions.{$student->id}.group_id", false);
});

function makeBulkPromoteGroupSubjectTestClasses(): array
{
    $fromClass = Classes::create(['name' => 'Bulk Optional Class 9', 'order' => 9, 'has_group' => true]);
    $toClass = Classes::create(['name' => 'Bulk Optional Class 10', 'order' => 10, 'has_group' => true]);
    $group = Group::create(['name' => 'Science', 'is_active' => true]);
    $fromClass->groups()->attach($group->id);
    $toClass->groups()->attach($group->id);

    $biology = Subject::create(['name' => 'Biology', 'has_written' => true, 'is_active' => true]);
    $higherMath = Subject::create(['name' => 'Higher Math', 'has_written' => true, 'is_active' => true]);

    foreach ([$fromClass, $toClass] as $class) {
        $biology->classes()->attach($class->id, ['group_id' => $group->id, 'subject_type' => SubjectType::Optional->value]);
        $higherMath->classes()->attach($class->id, ['group_id' => $group->id, 'subject_type' => SubjectType::Optional->value]);
    }

    return [$fromClass, $toClass, $group, $biology, $higherMath];
}

test('main/extra optional subject defaults to the current choice when the group carries over', function () {
    [$fromClass, $toClass, $group, $biology, $higherMath] = makeBulkPromoteGroupSubjectTestClasses();

    $student = createEnrolledStudent($fromClass, rollNo: 5, sessionYear: now()->year);
    $student->update(['current_group_id' => $group->id]);

    StudentOptionalSubject::create([
        'student_id' => $student->id,
        'class_id' => $fromClass->id,
        'group_id' => $group->id,
        'subject_id' => $biology->id,
        'role' => OptionalSubjectRole::MainOptional,
    ]);
    StudentOptionalSubject::create([
        'student_id' => $student->id,
        'class_id' => $fromClass->id,
        'group_id' => $group->id,
        'subject_id' => $higherMath->id,
        'role' => OptionalSubjectRole::ExtraOptional,
    ]);

    $this->actingAs(createAdminUser());

    Livewire::test(BulkPromoteStudentsForClass::class, ['classId' => $fromClass->id, 'year' => now()->year])
        ->assertSet("promotions.{$student->id}.main_optional_subject_id", $biology->id)
        ->assertSet("promotions.{$student->id}.extra_optional_subject_id", $higherMath->id);
});

test('confirming the bulk promotion as-is saves the carried-forward optional subjects for the new class', function () {
    [$fromClass, $toClass, $group, $biology, $higherMath] = makeBulkPromoteGroupSubjectTestClasses();

    $student = createEnrolledStudent($fromClass, rollNo: 5, sessionYear: now()->year);
    $student->update(['current_group_id' => $group->id]);

    StudentOptionalSubject::create([
        'student_id' => $student->id,
        'class_id' => $fromClass->id,
        'group_id' => $group->id,
        'subject_id' => $biology->id,
        'role' => OptionalSubjectRole::MainOptional,
    ]);
    StudentOptionalSubject::create([
        'student_id' => $student->id,
        'class_id' => $fromClass->id,
        'group_id' => $group->id,
        'subject_id' => $higherMath->id,
        'role' => OptionalSubjectRole::ExtraOptional,
    ]);

    $this->actingAs(createAdminUser());

    Livewire::test(BulkPromoteStudentsForClass::class, ['classId' => $fromClass->id, 'year' => now()->year])
        ->set("promotions.{$student->id}.roll_no", 1)
        ->call('promote');

    expect(StudentOptionalSubject::where('student_id', $student->id)
        ->where('class_id', $toClass->id)
        ->where('role', OptionalSubjectRole::MainOptional)
        ->value('subject_id'))->toBe($biology->id);

    expect(StudentOptionalSubject::where('student_id', $student->id)
        ->where('class_id', $toClass->id)
        ->where('role', OptionalSubjectRole::ExtraOptional)
        ->value('subject_id'))->toBe($higherMath->id);
});

test('bulk promote refuses to save when main and extra optional subject are the same for a row', function () {
    [$fromClass, $toClass, $group, $biology] = makeBulkPromoteGroupSubjectTestClasses();

    $student = createEnrolledStudent($fromClass, rollNo: 5, sessionYear: now()->year);
    $student->update(['current_group_id' => $group->id]);

    $this->actingAs(createAdminUser());

    Livewire::test(BulkPromoteStudentsForClass::class, ['classId' => $fromClass->id, 'year' => now()->year])
        ->set("promotions.{$student->id}.roll_no", 1)
        ->set("promotions.{$student->id}.main_optional_subject_id", $biology->id)
        ->set("promotions.{$student->id}.extra_optional_subject_id", $biology->id)
        ->call('promote');

    $student->refresh();

    // Nothing changed — the student was not actually promoted.
    expect($student->current_class_id)->toBe($fromClass->id);
    expect(StudentOptionalSubject::where('student_id', $student->id)->where('class_id', $toClass->id)->count())->toBe(0);
});

test('hides both selects when the class is terminal and students are graduating', function () {
    $class = Classes::create(['name' => 'Class 10 Terminal', 'order' => 10]); // no class ahead

    $student = createEnrolledStudent($class, rollNo: 1, sessionYear: now()->year);

    $response = $this->actingAs(createAdminUser())->get(
        BulkPromoteStudentsForClass::getUrl(['classId' => $class->id, 'year' => now()->year])
    );

    $response->assertOk()
        ->assertDontSee("promotions.{$student->id}.section_id", false)
        ->assertDontSee("promotions.{$student->id}.group_id", false);
});
