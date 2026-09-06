<?php

use App\Actions\BuildExamSchedulePdfAction;
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
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function createExamSchedulePdfTestExam(Classes $class): Exam
{
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    return Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => 2026,
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'is_published' => false,
    ]);
}

function createExamSchedulePdfTestSubject(Classes $class, string $name): Subject
{
    $subject = Subject::create(['name' => $name, 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    return $subject;
}

it('builds a valid schedule pdf listing every scheduled subject', function () {
    $class = Classes::create(['name' => 'Class Six', 'order' => 6, 'is_active' => true]);
    $exam = createExamSchedulePdfTestExam($class);
    $bangla = createExamSchedulePdfTestSubject($class, 'Bangla');
    $english = createExamSchedulePdfTestSubject($class, 'English');

    ExamSchedule::create(['exam_id' => $exam->id, 'subject_id' => $bangla->id, 'exam_date' => '2026-03-05']);
    ExamSchedule::create(['exam_id' => $exam->id, 'subject_id' => $english->id, 'exam_date' => '2026-03-07']);

    $pdf = app(BuildExamSchedulePdfAction::class)->handle($exam);

    expect($pdf)->toStartWith('%PDF');
});

it('builds a valid schedule pdf on A5 page size', function () {
    $class = Classes::create(['name' => 'Class Six', 'order' => 6, 'is_active' => true]);
    $exam = createExamSchedulePdfTestExam($class);
    $bangla = createExamSchedulePdfTestSubject($class, 'Bangla');

    ExamSchedule::create(['exam_id' => $exam->id, 'subject_id' => $bangla->id, 'exam_date' => '2026-03-05']);

    $pdf = app(BuildExamSchedulePdfAction::class)->handle($exam, 'A5');

    expect($pdf)->toStartWith('%PDF');
});

it('aborts with 404 when the exam has no schedule set yet', function () {
    $class = Classes::create(['name' => 'Class Six', 'order' => 6, 'is_active' => true]);
    $exam = createExamSchedulePdfTestExam($class);

    expect(fn () => app(BuildExamSchedulePdfAction::class)->handle($exam))
        ->toThrow(NotFoundHttpException::class);
});

it('streams the exam schedule pdf as a download with class, exam-type, and year in the filename', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Class Six', 'order' => 6, 'is_active' => true]);
    $exam = createExamSchedulePdfTestExam($class);
    $subject = createExamSchedulePdfTestSubject($class, 'Bangla');

    ExamSchedule::create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'exam_date' => '2026-03-05']);

    $response = $this->actingAs($admin)->get(route('exams.schedule.download', $exam));

    $response->assertStatus(Response::HTTP_OK);
    $response->assertHeader('Content-Type', 'application/pdf');

    $disposition = $response->headers->get('Content-Disposition');

    expect($disposition)->toContain('exam-schedule-Class-Six-Half-Yearly-2026.pdf');
});

it('hides the download button until at least one subject is scheduled', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class Six', 'order' => 6, 'is_active' => true]);
    $exam = createExamSchedulePdfTestExam($class);
    $subject = createExamSchedulePdfTestSubject($class, 'Bangla');

    Livewire::test(ScheduleRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->assertActionHidden(TestAction::make('downloadSchedule')->table());

    ExamSchedule::create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'exam_date' => '2026-03-05']);

    Livewire::test(ScheduleRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->assertActionVisible(TestAction::make('downloadSchedule')->table());
});
