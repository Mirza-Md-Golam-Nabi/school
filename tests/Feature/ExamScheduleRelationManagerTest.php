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
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
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

it('defaults to A5 page size when downloading the schedule', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class Six', 'order' => 6, 'is_active' => true]);
    $exam = createExamScheduleTestExam($class);
    $subject = createExamScheduleTestSubject($class);

    ExamSchedule::create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'exam_date' => '2026-03-05']);

    Livewire::test(ScheduleRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->callAction(TestAction::make('downloadSchedule')->table())
        ->assertRedirect(route('exams.schedule.download', ['exam' => $exam, 'pageSize' => 'A5', 'subjectLanguage' => 'en']));
});

it('redirects to the schedule download route with the selected page size', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class Six', 'order' => 6, 'is_active' => true]);
    $exam = createExamScheduleTestExam($class);
    $subject = createExamScheduleTestSubject($class);

    ExamSchedule::create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'exam_date' => '2026-03-05']);

    Livewire::test(ScheduleRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->callAction(TestAction::make('downloadSchedule')->table(), ['pageSize' => 'A5'])
        ->assertRedirect(route('exams.schedule.download', ['exam' => $exam, 'pageSize' => 'A5', 'subjectLanguage' => 'en']));
});

it('hides already scheduled subjects from the create form options', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class Six', 'order' => 6, 'is_active' => true]);
    $exam = createExamScheduleTestExam($class);
    $scheduledSubject = createExamScheduleTestSubject($class, 'Bangla');
    $freeSubject = createExamScheduleTestSubject($class, 'English');

    ExamSchedule::create(['exam_id' => $exam->id, 'subject_id' => $scheduledSubject->id, 'exam_date' => '2026-03-05']);

    Livewire::test(ScheduleRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->mountAction(TestAction::make(CreateAction::class)->table())
        ->assertFormFieldExists('subject_id', function (Select $field) use ($scheduledSubject, $freeSubject): bool {
            $options = $field->getOptions();

            return array_key_exists($freeSubject->id, $options) && ! array_key_exists($scheduledSubject->id, $options);
        });
});

it('keeps the current subject in the options when editing a schedule', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class Six', 'order' => 6, 'is_active' => true]);
    $exam = createExamScheduleTestExam($class);
    $subject = createExamScheduleTestSubject($class, 'Bangla');

    $schedule = ExamSchedule::create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'exam_date' => '2026-03-05']);

    Livewire::test(ScheduleRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->callAction(TestAction::make(EditAction::class)->table($schedule), [
            'subject_id' => $subject->id,
            'exam_date' => '2026-03-07',
        ])
        ->assertHasNoFormErrors();

    assertDatabaseHas(ExamSchedule::class, ['id' => $schedule->id, 'exam_date' => '2026-03-07']);
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
