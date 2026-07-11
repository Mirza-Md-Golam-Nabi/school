<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\UserType;
use App\Filament\Teacher\Pages\MySubjects;
use App\Filament\Teacher\Widgets\TeacherSubjectsOverview;
use App\Models\Classes;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\TeacherSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('counts distinct classes but every subject assignment, even when a subject repeats across classes', function () {
    $user = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);

    $teacher = TeacherProfile::create([
        'user_id' => $user->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    $classA = Classes::create(['name' => 'Class Three', 'order' => 3]);
    $classB = Classes::create(['name' => 'Class Four', 'order' => 4]);
    $sectionA = Section::create(['class_id' => $classA->id, 'name' => 'A']);
    $sectionB = Section::create(['class_id' => $classB->id, 'name' => 'A']);
    $bangla = Subject::create(['name' => 'Bangla']);
    $english = Subject::create(['name' => 'English']);

    $year = (int) now()->format('Y');

    TeacherSubject::create(['teacher_id' => $teacher->id, 'subject_id' => $bangla->id, 'class_id' => $classA->id, 'section_id' => $sectionA->id, 'session_year' => $year]);
    TeacherSubject::create(['teacher_id' => $teacher->id, 'subject_id' => $english->id, 'class_id' => $classA->id, 'section_id' => $sectionA->id, 'session_year' => $year]);
    TeacherSubject::create(['teacher_id' => $teacher->id, 'subject_id' => $bangla->id, 'class_id' => $classB->id, 'section_id' => $sectionB->id, 'session_year' => $year]);
    // Different session year — should not be counted.
    TeacherSubject::create(['teacher_id' => $teacher->id, 'subject_id' => $english->id, 'class_id' => $classB->id, 'section_id' => $sectionB->id, 'session_year' => $year - 1]);

    $this->actingAs($user);

    $data = (new TeacherSubjectsOverview)->getViewData();

    // Bangla is taught in both classA and classB, so it counts as two
    // separate teaching assignments, not one deduplicated subject.
    expect($data['classCount'])->toBe(2)
        ->and($data['subjectCount'])->toBe(3)
        ->and($data['url'])->toBe(MySubjects::getUrl(panel: 'teacher'));
});

it('renders zero counts when the teacher has no subject assignments', function () {
    $user = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);

    TeacherProfile::create([
        'user_id' => $user->id,
        'gender' => Gender::Female,
        'status' => EmploymentStatus::Active,
    ]);

    $this->actingAs($user);

    $data = (new TeacherSubjectsOverview)->getViewData();

    expect($data['classCount'])->toBe(0)
        ->and($data['subjectCount'])->toBe(0);
});

it('renders the widget on the dashboard linking to the my-subjects page', function () {
    $user = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);

    TeacherProfile::create([
        'user_id' => $user->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    $response = $this->actingAs($user)->get(route('filament.teacher.pages.dashboard'));

    $response->assertOk()
        ->assertSee('My Teaching Load')
        ->assertSee('href="'.MySubjects::getUrl(panel: 'teacher').'"', false);
});
