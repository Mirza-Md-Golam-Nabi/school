<?php

use App\Enums\OptionalSubjectRole;
use App\Models\Classes;
use App\Models\Group;
use App\Models\StudentOptionalSubject;
use App\Models\StudentProfile;
use Database\Seeders\ClassSeeder;
use Database\Seeders\ClassSubjectSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StudentSeeder;
use Database\Seeders\SubjectSeeder;
use Database\Seeders\TeacherSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

function seedStudentSeederPrerequisites(): void
{
    test()->seed(RoleSeeder::class);
    test()->seed(UserSeeder::class);
    test()->seed(TeacherSeeder::class);
    test()->seed(ClassSeeder::class);
    test()->seed(SubjectSeeder::class);
    test()->seed(ClassSubjectSeeder::class);
}

/** @return Collection<int, StudentProfile> */
function studentsInGroup(string $groupName): Collection
{
    $group = Group::where('name', $groupName)->firstOrFail();
    $class9And10 = Classes::whereIn('name', ['Class 9', 'Class 10'])->pluck('id');

    return StudentProfile::whereIn('current_class_id', $class9And10)
        ->where('current_group_id', $group->id)
        ->get();
}

it('gives every science-group student a main optional subject from Biology/Higher Mathematics only', function () {
    seedStudentSeederPrerequisites();
    test()->seed(StudentSeeder::class);

    $scienceStudents = studentsInGroup('Science');
    expect($scienceStudents)->not->toBeEmpty();

    $allowedMainNames = ['Biology', 'Higher Mathematics'];

    foreach ($scienceStudents as $student) {
        $main = StudentOptionalSubject::where('student_id', $student->id)
            ->where('role', OptionalSubjectRole::MainOptional)
            ->first();

        expect($main)->not->toBeNull()
            ->and($main->group_id)->toBe($student->current_group_id)
            ->and($main->subject->name)->toBeIn($allowedMainNames);
    }
});

it('never gives a science-group student a commerce/humanities-only subject', function () {
    seedStudentSeederPrerequisites();
    test()->seed(StudentSeeder::class);

    $forbidden = [
        'Accounting', 'Business Entrepreneurship', 'Finance and Banking',
        'History of Bangladesh and World Civilization', 'Civics and Citizenship', 'Geography and Environment',
    ];

    $scienceStudents = studentsInGroup('Science');

    foreach ($scienceStudents as $student) {
        $subjectNames = StudentOptionalSubject::where('student_id', $student->id)
            ->with('subject')
            ->get()
            ->pluck('subject.name');

        expect($subjectNames->intersect($forbidden))->toBeEmpty();
    }
});

it('gives every commerce/humanities student only an extra optional (no main optional — their group has no exclusive optional pool), and never a science-only subject', function () {
    seedStudentSeederPrerequisites();
    test()->seed(StudentSeeder::class);

    foreach (['Commerce', 'Humanities'] as $groupName) {
        $students = studentsInGroup($groupName);
        expect($students)->not->toBeEmpty();

        foreach ($students as $student) {
            $selections = StudentOptionalSubject::where('student_id', $student->id)->with('subject')->get();

            expect($selections->pluck('subject.name')->intersect(['Biology', 'Higher Mathematics']))->toBeEmpty()
                ->and($selections->firstWhere('role', OptionalSubjectRole::MainOptional))->toBeNull();

            $extra = $selections->firstWhere('role', OptionalSubjectRole::ExtraOptional);

            expect($extra)->not->toBeNull()
                ->and($extra->subject->name)->toBeIn(['Agricultural Education', 'Home Science', 'Economics'])
                ->and($extra->group_id)->toBe($student->current_group_id);
        }
    }
});

it('never assigns the same subject as both main and extra optional for the same student', function () {
    seedStudentSeederPrerequisites();
    test()->seed(StudentSeeder::class);

    $withBoth = StudentOptionalSubject::query()
        ->select('student_id')
        ->groupBy('student_id')
        ->havingRaw('COUNT(DISTINCT subject_id) < COUNT(*)')
        ->pluck('student_id');

    expect($withBoth)->toBeEmpty();
});
