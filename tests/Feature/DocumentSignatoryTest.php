<?php

use App\Actions\BuildStudentMarksDetail;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Filament\Pages\SchoolSettings;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\Marksheet;
use App\Models\SchoolSetting;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function renderSignatoryTestMarksheet(?TeacherProfile $classTeacher = null): string
{
    $class = Classes::create(['name' => 'Class One', 'order' => 1, 'class_teacher_id' => $classTeacher?->id]);
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
    ExamSubjectConfig::create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'written_total' => 100, 'total_marks' => 100, 'pass_mark' => 33]);

    $student = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 80]);
    $ranking = StudentMeritRanking::create(['exam_id' => $exam->id, 'student_id' => $student->id, 'class_id' => $class->id, 'total_marks' => 80, 'gpa' => 4.0, 'class_rank' => 1]);

    $marksheet = Marksheet::create(['student_id' => $student->id, 'exam_id' => $exam->id]);
    ['rows' => $rows, 'summary' => $summary] = app(BuildStudentMarksDetail::class)->handle($ranking);

    return view('documents.marksheet', compact('marksheet', 'rows', 'summary'))->render();
}

it('saves the head signatory titles from school settings', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create()));

    Livewire::test(SchoolSettings::class)
        ->fillForm([
            'school_name' => 'Test School',
            SchoolSetting::HEAD_TITLE_SETTING => 'Head Teacher',
            SchoolSetting::HEAD_TITLE_BN_SETTING => 'প্রধান',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SchoolSetting::get(SchoolSetting::HEAD_TITLE_SETTING))->toBe('Head Teacher')
        ->and(SchoolSetting::get(SchoolSetting::HEAD_TITLE_BN_SETTING))->toBe('প্রধান');
});

it('picks the head signatory title by locale with fallbacks', function () {
    expect(SchoolSetting::headSignatoryTitle())->toBe(SchoolSetting::DEFAULT_HEAD_TITLE);

    SchoolSetting::set(SchoolSetting::HEAD_TITLE_SETTING, 'Headmaster');
    expect(SchoolSetting::headSignatoryTitle())->toBe('Headmaster');

    App::setLocale('bn');
    expect(SchoolSetting::headSignatoryTitle())->toBe('Headmaster');

    SchoolSetting::set(SchoolSetting::HEAD_TITLE_BN_SETTING, 'প্রধান');
    expect(SchoolSetting::headSignatoryTitle())->toBe('প্রধান');
});

it('prints the configured head title instead of a fixed Principal on the marksheet', function () {
    SchoolSetting::set(SchoolSetting::HEAD_TITLE_SETTING, 'Head Teacher');

    expect(renderSignatoryTestMarksheet())
        ->toContain('Head Teacher')
        ->not->toContain('Principal');
});

it('prints the class teacher signature on the marksheet', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->image('signature.png', 300, 100)->store('teacher-profiles/signatures', 'public');
    $classTeacher = TeacherProfile::factory()->create(['signature' => $path]);

    expect(renderSignatoryTestMarksheet($classTeacher))->toContain('alt="Class Teacher Signature"');
});

it('leaves the class teacher signature blank when none is uploaded', function () {
    expect(renderSignatoryTestMarksheet(TeacherProfile::factory()->create()))
        ->not->toContain('alt="Class Teacher Signature"');
});
