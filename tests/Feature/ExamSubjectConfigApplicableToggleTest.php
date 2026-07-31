<?php

use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Filament\Resources\Exams\Pages\EditExam;
use App\Filament\Resources\Exams\RelationManagers\SubjectConfigsRelationManager;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamContributeRule;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\Subject;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function createExamConfigTestExam(Classes $class): Exam
{
    $examType = ExamType::create(['name' => 'Class Test', 'is_active' => true]);

    return Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => false,
    ]);
}

function attachExamConfigTestSubjectToClass(Subject $subject, Classes $class): void
{
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);
}

function createExamOfType(Classes $class, ExamType $examType): Exam
{
    return Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => false,
    ]);
}

it('lets an admin skip MCQ on a written-only exam instead of forcing a 0', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 6', 'order' => 6, 'is_active' => true]);
    $exam = createExamConfigTestExam($class);

    $subject = Subject::create(['name' => 'Bangla', 'has_mcq' => true, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    attachExamConfigTestSubjectToClass($subject, $class);

    Livewire::test(SubjectConfigsRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'subject_id' => $subject->id,
            'mcq_applicable' => false,
            'written_total' => 20,
            'total_marks' => 20,
            'pass_mark' => 8,
        ])
        ->assertHasNoFormErrors();

    assertDatabaseHas(ExamSubjectConfig::class, [
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'mcq_total' => null,
        'mcq_pass_mark' => null,
        'written_total' => 20,
    ]);
});

it('still lets both MCQ and Written be entered when both apply', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 7', 'order' => 7, 'is_active' => true]);
    $exam = createExamConfigTestExam($class);

    $subject = Subject::create(['name' => 'English', 'has_mcq' => true, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    attachExamConfigTestSubjectToClass($subject, $class);

    Livewire::test(SubjectConfigsRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'subject_id' => $subject->id,
            'mcq_total' => 30,
            'written_total' => 70,
            'total_marks' => 100,
            'pass_mark' => 33,
        ])
        ->assertHasNoFormErrors();

    assertDatabaseHas(ExamSubjectConfig::class, [
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'mcq_total' => 30,
        'written_total' => 70,
    ]);
});

it('requires the MCQ total only while MCQ is marked applicable', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 9', 'order' => 9, 'is_active' => true]);
    $exam = createExamConfigTestExam($class);

    $subject = Subject::create(['name' => 'Physics', 'has_mcq' => true, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    attachExamConfigTestSubjectToClass($subject, $class);

    Livewire::test(SubjectConfigsRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'subject_id' => $subject->id,
            'written_total' => 70,
            'total_marks' => 100,
            'pass_mark' => 33,
        ])
        ->assertHasFormErrors(['mcq_total' => 'required']);
});

it('defaults the applicable toggle to off when editing a config saved without MCQ', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 8', 'order' => 8, 'is_active' => true]);
    $exam = createExamConfigTestExam($class);

    $subject = Subject::create(['name' => 'Math', 'has_mcq' => true, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    attachExamConfigTestSubjectToClass($subject, $class);

    $config = ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'mcq_total' => null,
        'written_total' => 20,
        'total_marks' => 20,
        'pass_mark' => 8,
    ]);

    Livewire::test(SubjectConfigsRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->mountAction(TestAction::make(EditAction::class)->table($config))
        ->assertSchemaStateSet([
            'mcq_applicable' => false,
            'written_applicable' => true,
        ]);
});

it('defaults the applicable toggle to on when editing a config that already has an MCQ total', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 10', 'order' => 10, 'is_active' => true]);
    $exam = createExamConfigTestExam($class);

    $subject = Subject::create(['name' => 'Chemistry', 'has_mcq' => true, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    attachExamConfigTestSubjectToClass($subject, $class);

    $config = ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'mcq_total' => 30,
        'written_total' => 70,
        'total_marks' => 100,
        'pass_mark' => 33,
    ]);

    Livewire::test(SubjectConfigsRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->mountAction(TestAction::make(EditAction::class)->table($config))
        ->assertSchemaStateSet([
            'mcq_applicable' => true,
        ]);
});

it('shows the contributes_to_target toggle, defaulted on, when the exam type is an active contribution source', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 11', 'order' => 11, 'is_active' => true]);

    $classTestType = ExamType::create(['name' => 'Class Test', 'is_active' => true]);
    $halfYearlyType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    ExamContributeRule::create([
        'class_id' => $class->id,
        'source_exam_type_id' => $classTestType->id,
        'target_exam_type_id' => $halfYearlyType->id,
        'contribution_percent' => 20,
        'session_year' => now()->year,
    ]);

    $exam = createExamOfType($class, $classTestType);
    $subject = Subject::create(['name' => 'Bangla', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    attachExamConfigTestSubjectToClass($subject, $class);

    Livewire::test(SubjectConfigsRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'subject_id' => $subject->id,
            'written_total' => 10,
            'total_marks' => 10,
            'pass_mark' => 4,
        ])
        ->assertHasNoFormErrors();

    assertDatabaseHas(ExamSubjectConfig::class, [
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'contributes_to_target' => true,
    ]);
});

it('lets an admin turn off contributes_to_target for a specific subject', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 12', 'order' => 12, 'is_active' => true]);

    $classTestType = ExamType::create(['name' => 'Class Test', 'is_active' => true]);
    $halfYearlyType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    ExamContributeRule::create([
        'class_id' => $class->id,
        'source_exam_type_id' => $classTestType->id,
        'target_exam_type_id' => $halfYearlyType->id,
        'contribution_percent' => 20,
        'session_year' => now()->year,
    ]);

    $exam = createExamOfType($class, $classTestType);
    $subject = Subject::create(['name' => 'Bangla', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    attachExamConfigTestSubjectToClass($subject, $class);

    Livewire::test(SubjectConfigsRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'subject_id' => $subject->id,
            'written_total' => 10,
            'total_marks' => 10,
            'pass_mark' => 4,
            'contributes_to_target' => false,
        ])
        ->assertHasNoFormErrors();

    assertDatabaseHas(ExamSubjectConfig::class, [
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'contributes_to_target' => false,
    ]);
});

it('does not show the contributes_to_target toggle when the exam type has no active contribution rule', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 13', 'order' => 13, 'is_active' => true]);
    $exam = createExamConfigTestExam($class);

    $subject = Subject::create(['name' => 'Bangla', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    attachExamConfigTestSubjectToClass($subject, $class);

    Livewire::test(SubjectConfigsRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->mountAction(TestAction::make(CreateAction::class)->table())
        ->fillForm(['subject_id' => $subject->id])
        ->assertFormFieldDoesNotExist('contributes_to_target');
});
