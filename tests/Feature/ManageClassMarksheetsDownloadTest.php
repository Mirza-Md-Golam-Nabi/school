<?php

use App\Enums\Gender;
use App\Enums\MarksheetBundleType;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Filament\Resources\Marksheets\Pages\ManageClassMarksheets;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\Marksheet;
use App\Models\MarksheetBundle;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeManageClassMarksheetsTestStudent(int $classId, ?int $rollNo = null): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => $rollNo ?? random_int(1, 100000),
        'current_class_id' => $classId,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function makeManageClassMarksheetsTestExam(int $classId): Exam
{
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    return Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $classId,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
    ]);
}

/**
 * Two compulsory subjects; one student passes both, one fails one subject,
 * one fails both — every one with a ranking and a generated marksheet.
 */
function seedManageClassMarksheetsTestResults(Classes $class, Exam $exam): void
{
    $subjects = collect(['Bangla', 'English'])->map(function (string $name) use ($class, $exam) {
        $subject = Subject::create(['name' => $name, 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
        $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

        ExamSubjectConfig::create([
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'written_total' => 100,
            'total_marks' => 100,
            'pass_mark' => 33,
        ]);

        return $subject;
    });

    foreach ([0, 1, 2] as $failedCount) {
        $student = makeManageClassMarksheetsTestStudent($class->id, rollNo: $failedCount + 1);

        foreach ($subjects as $index => $subject) {
            StudentResult::create([
                'exam_id' => $exam->id,
                'subject_id' => $subject->id,
                'class_id' => $class->id,
                'student_id' => $student->id,
                'written_marks' => $index < $failedCount ? 20 : 80,
            ]);
        }

        StudentMeritRanking::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'class_id' => $class->id,
            'total_marks' => 160,
            'gpa' => 4.0,
            'class_rank' => $failedCount + 1,
        ]);

        Marksheet::create(['student_id' => $student->id, 'exam_id' => $exam->id, 'is_generated' => true]);
    }
}

beforeEach(function () {
    test()->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));
});

it('shows a danger notification when no marksheet has been generated yet', function () {
    $class = Classes::create(['name' => 'Marksheet Download Notify Class', 'order' => 1]);
    $exam = makeManageClassMarksheetsTestExam($class->id);

    Livewire::test(ManageClassMarksheets::class, ['classId' => $class->id])
        ->mountAction('downloadMarksheets')
        ->setActionData(['exam_id' => $exam->id, 'pattern' => MarksheetBundleType::All->value, 'subjectLanguage' => 'en'])
        ->callMountedAction()
        ->assertNotified()
        ->assertNoFileDownloaded();
});

it('serves the stored bundle file instantly when it is ready in the chosen language', function () {
    Storage::fake('local');

    $class = Classes::create(['name' => 'Marksheet Bundle Ready Class', 'order' => 1]);
    $exam = makeManageClassMarksheetsTestExam($class->id);

    Storage::disk('local')->put('documents/marksheets/bundle-passed.pdf', '%PDF-stored');

    MarksheetBundle::create([
        'exam_id' => $exam->id,
        'type' => MarksheetBundleType::Passed,
        'subject_language' => 'en',
        'file_path' => 'documents/marksheets/bundle-passed.pdf',
        'student_count' => 5,
        'is_generated' => true,
    ]);

    Livewire::test(ManageClassMarksheets::class, ['classId' => $class->id])
        ->mountAction('downloadMarksheets')
        ->setActionData(['exam_id' => $exam->id, 'pattern' => MarksheetBundleType::Passed->value, 'subjectLanguage' => 'en'])
        ->callMountedAction()
        ->assertFileDownloaded("marksheets-{$class->id}-{$exam->id}-passed.pdf", '%PDF-stored');
});

it('builds the pdf on the fly when the stored bundle is in a different language', function () {
    Storage::fake('local');

    $class = Classes::create(['name' => 'Marksheet Bundle Language Class', 'order' => 1]);
    $exam = makeManageClassMarksheetsTestExam($class->id);
    seedManageClassMarksheetsTestResults($class, $exam);

    Storage::disk('local')->put('documents/marksheets/bundle-all.pdf', '%PDF-stored');

    MarksheetBundle::create([
        'exam_id' => $exam->id,
        'type' => MarksheetBundleType::All,
        'subject_language' => 'bn',
        'file_path' => 'documents/marksheets/bundle-all.pdf',
        'student_count' => 3,
        'is_generated' => true,
    ]);

    Livewire::test(ManageClassMarksheets::class, ['classId' => $class->id])
        ->mountAction('downloadMarksheets')
        ->setActionData(['exam_id' => $exam->id, 'pattern' => MarksheetBundleType::All->value, 'subjectLanguage' => 'en'])
        ->callMountedAction()
        ->assertFileDownloaded("marksheets-{$class->id}-{$exam->id}.pdf");
});

it('downloads only students who failed in the chosen number of subjects', function () {
    $class = Classes::create(['name' => 'Marksheet Failed Count Class', 'order' => 1]);
    $exam = makeManageClassMarksheetsTestExam($class->id);
    seedManageClassMarksheetsTestResults($class, $exam);

    Livewire::test(ManageClassMarksheets::class, ['classId' => $class->id])
        ->mountAction('downloadMarksheets')
        ->setActionData([
            'exam_id' => $exam->id,
            'pattern' => ManageClassMarksheets::PATTERN_FAILED_BY_SUBJECT_COUNT,
            'failed_from' => 1,
            'failed_to' => 2,
            'subjectLanguage' => 'en',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertFileDownloaded("marksheets-{$class->id}-{$exam->id}-failed-1-to-2-subjects.pdf");
});

it('shows a danger notification when no student failed in the chosen number of subjects', function () {
    $class = Classes::create(['name' => 'Marksheet Failed Count Empty Class', 'order' => 1]);
    $exam = makeManageClassMarksheetsTestExam($class->id);
    seedManageClassMarksheetsTestResults($class, $exam);

    Livewire::test(ManageClassMarksheets::class, ['classId' => $class->id])
        ->mountAction('downloadMarksheets')
        ->setActionData([
            'exam_id' => $exam->id,
            'pattern' => ManageClassMarksheets::PATTERN_FAILED_BY_SUBJECT_COUNT,
            'failed_from' => 3,
            'failed_to' => null,
            'subjectLanguage' => 'en',
        ])
        ->callMountedAction()
        ->assertNotified()
        ->assertNoFileDownloaded();
});

it('requires the failed-subject count for the failed-by-subject-count pattern', function () {
    $class = Classes::create(['name' => 'Marksheet Failed Count Required Class', 'order' => 1]);
    $exam = makeManageClassMarksheetsTestExam($class->id);

    Livewire::test(ManageClassMarksheets::class, ['classId' => $class->id])
        ->mountAction('downloadMarksheets')
        ->setActionData([
            'exam_id' => $exam->id,
            'pattern' => ManageClassMarksheets::PATTERN_FAILED_BY_SUBJECT_COUNT,
            'failed_from' => null,
            'subjectLanguage' => 'en',
        ])
        ->callMountedAction()
        ->assertHasFormErrors(['failed_from' => 'required']);
});

it('notifies instead of downloading when the ready bundle holds no student', function () {
    $class = Classes::create(['name' => 'Marksheet Empty Bundle Class', 'order' => 1]);
    $exam = makeManageClassMarksheetsTestExam($class->id);

    MarksheetBundle::create([
        'exam_id' => $exam->id,
        'type' => MarksheetBundleType::Failed,
        'subject_language' => 'en',
        'student_count' => 0,
        'is_generated' => true,
    ]);

    Livewire::test(ManageClassMarksheets::class, ['classId' => $class->id])
        ->mountAction('downloadMarksheets')
        ->setActionData(['exam_id' => $exam->id, 'pattern' => MarksheetBundleType::Failed->value, 'subjectLanguage' => 'en'])
        ->callMountedAction()
        ->assertNotified()
        ->assertNoFileDownloaded();
});

it('puts a green check mark next to a pattern whose bundle is ready in the chosen language', function () {
    $class = Classes::create(['name' => 'Marksheet Check Mark Class', 'order' => 1]);
    $exam = makeManageClassMarksheetsTestExam($class->id);

    MarksheetBundle::create([
        'exam_id' => $exam->id,
        'type' => MarksheetBundleType::Failed,
        'subject_language' => 'en',
        'student_count' => 0,
        'is_generated' => true,
    ]);

    $component = Livewire::test(ManageClassMarksheets::class, ['classId' => $class->id])
        ->mountAction('downloadMarksheets');

    $patternOptions = fn (string $subjectLanguage): array => $component
        ->set('mountedActions.0.data.subjectLanguage', $subjectLanguage)
        ->instance()
        ->getSchema($component->instance()->getMountedActionSchemaName())
        ->getFlatFields(withHidden: true)['pattern']
        ->getOptions();

    $englishOptions = $patternOptions('en');

    expect((string) $englishOptions[MarksheetBundleType::Failed->value])->toContain('title="তৈরি আছে"')
        ->and((string) $englishOptions[MarksheetBundleType::All->value])->not->toContain('title="তৈরি আছে"')
        ->and((string) $patternOptions('bn')[MarksheetBundleType::Failed->value])->not->toContain('title="তৈরি আছে"');
});
