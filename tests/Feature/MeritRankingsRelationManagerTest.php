<?php

use App\Actions\ApplyExamSubjectContributions;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Filament\Resources\Exams\Pages\EditExam;
use App\Filament\Resources\Exams\RelationManagers\MeritRankingsRelationManager;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamContributeRule;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function makeMeritRankingTestStudent(int $classId): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 5,
        'current_class_id' => $classId,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

it('opens the marks detail modal without error when no contribution rule applies', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

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

    $subject = Subject::create(['name' => 'Bangla', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    $student = makeMeritRankingTestStudent($class->id);

    ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'written_total' => 100,
        'total_marks' => 100,
        'pass_mark' => 33,
    ]);

    StudentResult::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'student_id' => $student->id,
        'written_marks' => 78,
    ]);

    $ranking = StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'total_marks' => 78,
        'gpa' => 4.5,
        'class_rank' => 1,
    ]);

    Livewire::test(MeritRankingsRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->mountAction(TestAction::make('viewMarks')->table($ranking))
        ->assertOk();
});

it('opens the marks detail modal without error when a contribution rule applies', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class One', 'order' => 1]);

    $subject = Subject::create(['name' => 'Bangla', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    $classTestType = ExamType::create(['name' => 'Class Test', 'is_active' => true]);
    $halfYearlyType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    ExamContributeRule::create([
        'class_id' => $class->id,
        'source_exam_type_id' => $classTestType->id,
        'target_exam_type_id' => $halfYearlyType->id,
        'contribution_percent' => 20,
        'session_year' => now()->year,
    ]);

    $student = makeMeritRankingTestStudent($class->id);

    $classTest = Exam::create([
        'exam_type_id' => $classTestType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => true,
    ]);
    ExamSubjectConfig::create([
        'exam_id' => $classTest->id,
        'subject_id' => $subject->id,
        'written_total' => 10,
        'total_marks' => 10,
        'pass_mark' => 4,
    ]);
    StudentResult::create([
        'exam_id' => $classTest->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'student_id' => $student->id,
        'written_marks' => 9,
    ]);

    $halfYearly = Exam::create([
        'exam_type_id' => $halfYearlyType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => true,
    ]);
    ExamSubjectConfig::create([
        'exam_id' => $halfYearly->id,
        'subject_id' => $subject->id,
        'written_total' => 80,
        'total_marks' => 80,
        'pass_mark' => 26,
    ]);
    StudentResult::create([
        'exam_id' => $halfYearly->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'student_id' => $student->id,
        'written_marks' => 65,
    ]);

    app(ApplyExamSubjectContributions::class)->execute($halfYearly);

    $ranking = StudentMeritRanking::create([
        'exam_id' => $halfYearly->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'total_marks' => 83,
        'gpa' => 5.0,
        'class_rank' => 1,
    ]);

    Livewire::test(MeritRankingsRelationManager::class, ['ownerRecord' => $halfYearly, 'pageClass' => EditExam::class])
        ->mountAction(TestAction::make('viewMarks')->table($ranking))
        ->assertOk();
});

it('hides the section rank and section columns/filter when the class has no sections', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Sectionless Class', 'order' => 1]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'is_published' => true,
    ]);

    Livewire::test(MeritRankingsRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->assertTableColumnHidden('section_rank')
        ->assertTableColumnHidden('section.name')
        ->assertTableFilterHidden('section_id');
});

it('shows the section rank and section columns/filter when the class has sections', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Sectioned Class', 'order' => 1]);
    $class->sections()->create(['name' => 'A']);

    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'is_published' => true,
    ]);

    Livewire::test(MeritRankingsRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->assertTableColumnVisible('section_rank')
        ->assertTableColumnVisible('section.name')
        ->assertTableFilterVisible('section_id');
});
