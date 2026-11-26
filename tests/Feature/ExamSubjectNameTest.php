<?php

use App\Actions\BuildExamResultReport;
use App\Actions\BuildExamTabulationSheetDetail;
use App\Actions\BuildStudentMarksDetail;
use App\Actions\CalculateExamRankings;
use App\Enums\Gender;
use App\Enums\MarksheetBundleType;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Filament\Resources\Exams\Pages\EditExam;
use App\Filament\Resources\Exams\RelationManagers\SubjectConfigsRelationManager;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\Marksheet;
use App\Models\MarksheetBundle;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * An exam of Class 8 with one subject, Agriculture, and one ranked student.
 *
 * @return array{exam: Exam, subject: Subject, config: ExamSubjectConfig, student: StudentProfile}
 */
function makeExamSubjectNameExam(?string $examName = null, ?string $examNameBn = null): array
{
    $class = Classes::create(['name' => 'Class 8', 'order' => 8, 'is_active' => true]);
    $subject = Subject::create(['name' => 'Agriculture', 'name_bn' => 'কৃষি', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['group_id' => null, 'subject_type' => SubjectType::Compulsory->value]);

    $exam = Exam::create([
        'exam_type_id' => ExamType::create(['name' => 'Half Yearly', 'is_active' => true])->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
    ]);

    $config = ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'subject_name' => $examName,
        'subject_name_bn' => $examNameBn,
        'written_total' => 100,
        'total_marks' => 100,
        'pass_mark' => 33,
    ]);

    $student = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => $exam->session_year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 20]);
    app(CalculateExamRankings::class)->execute($exam);

    return compact('exam', 'subject', 'config', 'student');
}

it('prints the name written for the exam on its marksheet, tabulation sheet, result report and routine', function () {
    ['exam' => $exam, 'subject' => $subject, 'student' => $student] = makeExamSubjectNameExam('Agriculture Studies');
    ExamSchedule::create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'exam_date' => now()->toDateString()]);

    $ranking = StudentMeritRanking::where('exam_id', $exam->id)->where('student_id', $student->id)->sole();

    expect(app(BuildStudentMarksDetail::class)->handle($ranking)['rows']->sole()['subject_name'])->toBe('Agriculture Studies')
        ->and(app(BuildExamTabulationSheetDetail::class)->handle($exam)['columns']->sole()['label'])->toBe('Agriculture Studies')
        ->and(app(BuildExamResultReport::class)->handle($exam)['subjectRows'][0]['label'])->toBe('Agriculture Studies')
        ->and($exam->fresh()->admitCardRoutineColumns()[0][0]['subject'])->toBe('Agriculture Studies')
        ->and($subject->fresh()->display_name)->toBe('Agriculture');
});

it('falls back to the subject\'s own name, and on Bangla papers to the exam\'s English name', function () {
    ['exam' => $exam, 'subject' => $subject, 'config' => $config] = makeExamSubjectNameExam();

    expect($exam->subjectDisplayName($subject))->toBe('Agriculture');

    app()->setLocale('bn');

    expect($exam->subjectDisplayName($subject))->toBe('কৃষি');

    $config->update(['subject_name' => 'Agriculture Studies']);

    expect($exam->fresh()->subjectDisplayName($subject))->toBe('Agriculture Studies');

    $config->update(['subject_name_bn' => 'কৃষিশিক্ষা']);

    expect($exam->fresh()->subjectDisplayName($subject))->toBe('কৃষিশিক্ষা');
});

it('marks the exam\'s generated PDFs outdated only when a subject\'s exam name changes', function () {
    ['exam' => $exam, 'config' => $config, 'student' => $student] = makeExamSubjectNameExam();

    $marksheet = Marksheet::create(['student_id' => $student->id, 'exam_id' => $exam->id, 'is_generated' => true]);
    $bundle = MarksheetBundle::create(['exam_id' => $exam->id, 'type' => MarksheetBundleType::All, 'is_generated' => true, 'generation_token' => (string) Str::uuid()]);

    $config->update(['pass_mark' => 40]);

    expect($marksheet->fresh()->is_generated)->toBeTrue();

    $config->update(['subject_name' => 'Agriculture Studies']);

    expect($marksheet->fresh()->is_generated)->toBeFalse()
        ->and($bundle->fresh())
        ->is_generated->toBeFalse()
        ->generation_token->toBeNull();
});

it('lets the admin set a subject\'s name for one exam and tells them to regenerate the PDFs', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    ['exam' => $exam, 'config' => $config] = makeExamSubjectNameExam();

    Livewire::test(SubjectConfigsRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->callAction(TestAction::make(EditAction::class)->table($config), [
            'subject_name' => 'Agriculture Studies',
            'subject_name_bn' => 'কৃষিশিক্ষা',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('সাবজেক্টের নাম বদলেছে');

    expect($config->fresh())
        ->subject_name->toBe('Agriculture Studies')
        ->subject_name_bn->toBe('কৃষিশিক্ষা');
});
