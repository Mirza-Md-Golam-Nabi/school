<?php

use App\Actions\BuildStudentMarksDetail;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\Subjects\Pages\ListSubjects;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeOrderTestSubject(string $name, ?int $sortOrder = null): Subject
{
    return Subject::create(array_filter([
        'name' => $name,
        'sort_order' => $sortOrder,
        'has_mcq' => false,
        'has_written' => true,
        'has_practical' => false,
        'is_active' => true,
    ], fn (mixed $value): bool => $value !== null));
}

it('puts a new subject after every existing one', function () {
    makeOrderTestSubject('Bangla', 5);
    $english = makeOrderTestSubject('English');

    expect($english->sort_order)->toBe(6);
});

it('lists subjects by sort_order, then id', function () {
    $math = makeOrderTestSubject('Mathematics', 2);
    $bangla = makeOrderTestSubject('Bangla', 1);
    $english = makeOrderTestSubject('English', 2);

    expect(Subject::ordered()->pluck('id')->all())->toBe([$bangla->id, $math->id, $english->id]);
});

it('builds marksheet rows in the subject order, not the order results were entered', function () {
    $class = Classes::create(['name' => 'Class Ten', 'order' => 10, 'is_active' => true]);
    $examType = ExamType::create(['name' => 'Annual', 'is_active' => true]);
    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => true,
    ]);

    $math = makeOrderTestSubject('Mathematics', 3);
    $bangla = makeOrderTestSubject('Bangla', 1);
    $english = makeOrderTestSubject('English', 2);

    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    foreach ([$math, $english, $bangla] as $subject) {
        StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 70]);
    }

    $ranking = StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'total_marks' => 210,
        'gpa' => 4.0,
        'class_rank' => 1,
    ]);

    ['rows' => $rows] = app(BuildStudentMarksDetail::class)->handle($ranking);

    expect($rows->pluck('subject_name')->all())->toBe(['Bangla', 'English', 'Mathematics']);
});

it('lets an admin reorder subjects from the subjects table', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $bangla = makeOrderTestSubject('Bangla', 1);
    $english = makeOrderTestSubject('English', 2);

    Livewire::actingAs($admin)
        ->test(ListSubjects::class)
        ->call('reorderTable', [(string) $english->id, (string) $bangla->id]);

    expect(Subject::ordered()->pluck('id')->all())->toBe([$english->id, $bangla->id]);
});
