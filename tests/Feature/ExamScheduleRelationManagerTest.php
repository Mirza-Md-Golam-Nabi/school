<?php

use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Filament\Resources\Exams\Pages\EditExam;
use App\Filament\Resources\Exams\RelationManagers\ScheduleRelationManager;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\ExamType;
use App\Models\Subject;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function createExamScheduleTestExam(Classes $class): Exam
{
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    return Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'is_published' => false,
    ]);
}

function createExamScheduleTestSubject(Classes $class, string $name = 'Bangla'): Subject
{
    $subject = Subject::create(['name' => $name, 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    return $subject;
}

it('lets an admin schedule a subject exam date within the exam period', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class Six', 'order' => 6, 'is_active' => true]);
    $exam = createExamScheduleTestExam($class);
    $subject = createExamScheduleTestSubject($class);

    Livewire::test(ScheduleRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'subject_id' => $subject->id,
            'exam_date' => '2026-03-05',
        ])
        ->assertHasNoFormErrors();

    assertDatabaseHas(ExamSchedule::class, [
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'exam_date' => '2026-03-05',
    ]);
});

it('rejects a schedule date outside the exam period', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class Six', 'order' => 6, 'is_active' => true]);
    $exam = createExamScheduleTestExam($class);
    $subject = createExamScheduleTestSubject($class);

    Livewire::test(ScheduleRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'subject_id' => $subject->id,
            'exam_date' => '2026-04-01',
        ])
        ->assertHasFormErrors(['exam_date']);

    expect(ExamSchedule::where('exam_id', $exam->id)->exists())->toBeFalse();
});

it('rejects scheduling the same subject twice for one exam', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class Six', 'order' => 6, 'is_active' => true]);
    $exam = createExamScheduleTestExam($class);
    $subject = createExamScheduleTestSubject($class);

    ExamSchedule::create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'exam_date' => '2026-03-05']);

    Livewire::test(ScheduleRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'subject_id' => $subject->id,
            'exam_date' => '2026-03-08',
        ])
        ->assertHasFormErrors(['subject_id']);

    expect(ExamSchedule::where('exam_id', $exam->id)->count())->toBe(1);
});

it('only offers subjects assigned to the exam class', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class Six', 'order' => 6, 'is_active' => true]);
    $otherClass = Classes::create(['name' => 'Class Seven', 'order' => 7, 'is_active' => true]);
    $exam = createExamScheduleTestExam($class);

    $ownSubject = createExamScheduleTestSubject($class, 'Bangla');
    $otherSubject = createExamScheduleTestSubject($otherClass, 'English');

    Livewire::test(ScheduleRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'subject_id' => $otherSubject->id,
            'exam_date' => '2026-03-05',
        ])
        ->assertHasFormErrors(['subject_id']);

    expect(ExamSchedule::where('subject_id', $ownSubject->id)->exists())->toBeFalse();
});
