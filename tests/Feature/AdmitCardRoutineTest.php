<?php

use App\Actions\BuildCombinedClassAdmitCardsPdfAction;
use App\Actions\GenerateAdmitCardsForExamAction;
use App\Actions\GenerateAdmitCardsForExamTypeAction;
use App\Enums\AdmitCardDesign;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Jobs\GenerateAdmitCardPdfJob;
use App\Models\AdmitCard;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\ExamType;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeRoutineTestExam(Classes $class, ?ExamType $examType = null): Exam
{
    return Exam::create([
        'exam_type_id' => ($examType ?? ExamType::create(['name' => 'Second Tutorial', 'is_active' => true]))->id,
        'class_id' => $class->id,
        'session_year' => 2026,
        'start_date' => '2026-01-02',
        'end_date' => '2026-01-12',
        'is_published' => true,
    ]);
}

/**
 * @param  array<string, string>  $subjectDates  subject name → exam date
 */
function addRoutineTestSchedule(Exam $exam, array $subjectDates): void
{
    foreach ($subjectDates as $name => $date) {
        $subject = Subject::create(['name' => $name, 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
        ExamSchedule::create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'exam_date' => $date]);
    }
}

function makeRoutineTestStudent(Classes $class, int $roll = 1): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => $roll,
        'current_class_id' => $class->id,
        'session_year' => 2026,
        'gender' => Gender::Female,
        'status' => StudentStatus::Active,
    ]);
}

it('splits 7 subjects into 4 on the left and 3 on the right, numbered in date order', function () {
    $exam = makeRoutineTestExam(Classes::create(['name' => 'Class Three', 'order' => 3]));
    addRoutineTestSchedule($exam, [
        'Science' => '2026-01-08',
        'Mathematics' => '2026-01-02',
        'English' => '2026-01-03',
        'Bangla' => '2026-01-05',
        'Religion' => '2026-01-06',
        'ICT' => '2026-01-12',
        'BGS' => '2026-01-10',
    ]);

    [$left, $right] = $exam->admitCardRoutineColumns();

    expect($left)->toBe([
        ['serial' => 1, 'date' => '02 Jan 2026', 'subject' => 'Mathematics'],
        ['serial' => 2, 'date' => '03 Jan 2026', 'subject' => 'English'],
        ['serial' => 3, 'date' => '05 Jan 2026', 'subject' => 'Bangla'],
        ['serial' => 4, 'date' => '06 Jan 2026', 'subject' => 'Religion'],
    ])->and($right)->toBe([
        ['serial' => 5, 'date' => '08 Jan 2026', 'subject' => 'Science'],
        ['serial' => 6, 'date' => '10 Jan 2026', 'subject' => 'BGS'],
        ['serial' => 7, 'date' => '12 Jan 2026', 'subject' => 'ICT'],
    ]);
});

it('splits an even number of subjects evenly, and a single subject to the left', function (int $subjects, int $leftCount, int $rightCount) {
    $exam = makeRoutineTestExam(Classes::create(['name' => 'Class Three', 'order' => 3]));
    addRoutineTestSchedule($exam, collect(range(1, $subjects))->mapWithKeys(fn (int $day): array => ["Subject {$day}" => "2026-01-0{$day}"])->all());

    [$left, $right] = $exam->admitCardRoutineColumns();

    expect($left)->toHaveCount($leftCount)->and($right)->toHaveCount($rightCount);
})->with([
    'six' => [6, 3, 3],
    'one' => [1, 1, 0],
]);

it('prints the routine with dates and subjects but no time, and Powered by Next Fusion', function () {
    $class = Classes::create(['name' => 'Class Three', 'order' => 3]);
    $exam = makeRoutineTestExam($class);
    addRoutineTestSchedule($exam, ['Mathematics' => '2026-01-02', 'English' => '2026-01-03']);
    $admitCard = AdmitCard::create(['student_id' => makeRoutineTestStudent($class)->id, 'exam_id' => $exam->id, 'page_size' => 'A4', 'design' => AdmitCardDesign::WithRoutine]);

    $html = view('documents.admit-card-routine', ['admitCard' => $admitCard->load('exam.schedules.subject')])->render();

    expect($html)->toContain('EXAMINATION ROUTINE', '02 Jan 2026', 'Mathematics', '03 Jan 2026', 'English', 'Next Fusion')
        ->not->toContain('AM', 'PM');
});

it('adds Powered by Next Fusion to the simple admit card too', function () {
    $class = Classes::create(['name' => 'Class Three', 'order' => 3]);
    $admitCard = AdmitCard::create(['student_id' => makeRoutineTestStudent($class)->id, 'exam_id' => makeRoutineTestExam($class)->id, 'page_size' => 'A4']);

    expect(view('documents.admit-card', ['admitCard' => $admitCard])->render())->toContain('Powered by: Next Fusion');
});

it('generates no routine card for an exam without an exam schedule', function () {
    Queue::fake();
    $class = Classes::create(['name' => 'Class Three', 'order' => 3]);
    makeRoutineTestStudent($class);
    $exam = makeRoutineTestExam($class);

    $result = app(GenerateAdmitCardsForExamAction::class)->handle($exam, design: AdmitCardDesign::WithRoutine);

    expect($result['without_routine'])->toBeTrue()
        ->and(AdmitCard::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('switches cards of the other design to the chosen one and generates them again', function () {
    Queue::fake();
    $class = Classes::create(['name' => 'Class Three', 'order' => 3]);
    $student = makeRoutineTestStudent($class);
    $exam = makeRoutineTestExam($class);
    addRoutineTestSchedule($exam, ['Mathematics' => '2026-01-02']);
    $card = AdmitCard::create(['student_id' => $student->id, 'exam_id' => $exam->id, 'page_size' => 'A4', 'design' => AdmitCardDesign::Simple, 'is_generated' => true]);

    $result = app(GenerateAdmitCardsForExamAction::class)->handle($exam, pageSize: 'A5', design: AdmitCardDesign::WithRoutine);

    expect($result)->toBe(['created' => 0, 'updated' => 1, 'skipped' => 0, 'without_routine' => false])
        ->and($card->refresh()->design)->toBe(AdmitCardDesign::WithRoutine)
        ->and($card->page_size)->toBe('A5')
        ->and($card->is_generated)->toBeFalse();
    Queue::assertPushed(GenerateAdmitCardPdfJob::class, fn (GenerateAdmitCardPdfJob $job): bool => $job->admitCardId === $card->id);
});

it('names the classes left without routine cards when generating for a whole exam type', function () {
    Queue::fake();
    $examType = ExamType::create(['name' => 'Second Tutorial', 'is_active' => true]);
    $classThree = Classes::create(['name' => 'Class Three', 'order' => 3]);
    $classFour = Classes::create(['name' => 'Class Four', 'order' => 4]);
    makeRoutineTestStudent($classThree);
    makeRoutineTestStudent($classFour);
    addRoutineTestSchedule(makeRoutineTestExam($classThree, $examType), ['Mathematics' => '2026-01-02']);
    makeRoutineTestExam($classFour, $examType);

    $result = app(GenerateAdmitCardsForExamTypeAction::class)->handle($examType, design: AdmitCardDesign::WithRoutine);

    expect($result['created'])->toBe(1)
        ->and($result['classes_without_routine'])->toBe(['Class Four']);
});

it('generates the routine card PDF and the class sheet with it', function () {
    Storage::fake('local');
    $class = Classes::create(['name' => 'Class Three', 'order' => 3]);
    $exam = makeRoutineTestExam($class);
    addRoutineTestSchedule($exam, ['Mathematics' => '2026-01-02', 'English' => '2026-01-03', 'Bangla' => '2026-01-04']);

    $cards = collect([1, 2, 3])->map(fn (int $roll): AdmitCard => AdmitCard::create([
        'student_id' => makeRoutineTestStudent($class, $roll)->id,
        'exam_id' => $exam->id,
        'page_size' => 'A5',
        'design' => AdmitCardDesign::WithRoutine,
    ]));

    $cards->each(fn (AdmitCard $card) => (new GenerateAdmitCardPdfJob($card->id))->handle());

    expect($cards->every(fn (AdmitCard $card): bool => $card->refresh()->is_generated))->toBeTrue();
    Storage::disk('local')->assertExists($cards->first()->file_path);

    expect(app(BuildCombinedClassAdmitCardsPdfAction::class)->handle($class, $exam))->toStartWith('%PDF');
});

it('shows the admin a sample PDF of each design without saving anything', function (AdmitCardDesign $design) {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $response = $this->get(route('admit-cards.sample', $design));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF')
        ->and(AdmitCard::count())->toBe(0)
        ->and(Exam::count())->toBe(0)
        ->and(StudentProfile::count())->toBe(0);
})->with([AdmitCardDesign::Simple, AdmitCardDesign::WithRoutine]);

it('keeps the design samples behind login', function () {
    expect(app('router')->getRoutes()->getByName('admit-cards.sample')->gatherMiddleware())->toContain('auth');
});
