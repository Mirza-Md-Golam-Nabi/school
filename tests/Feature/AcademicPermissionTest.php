<?php

use App\Enums\PermissionRegistry;
use App\Enums\UserType;
use App\Filament\Pages\EnterStudentMarks;
use App\Filament\Resources\Classes\ClassesResource;
use App\Filament\Resources\Exams\ExamResource;
use App\Filament\Resources\ExamTypes\ExamTypeResource;
use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\ResultRules\ResultRuleResource;
use App\Filament\Resources\Subjects\Pages\ListSubjects;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Models\Permission;
use App\Models\User;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ((new PermissionRegistry)() as $permissionSet) {
        foreach ($permissionSet as $permission) {
            Permission::firstOrCreate(['name' => $permission->value, 'guard_name' => 'web']);
        }
    }
});

function academicUser(string ...$permissions): User
{
    $user = User::factory()->create(['user_type' => UserType::Staff, 'is_active' => true]);
    $user->givePermissionTo($permissions);

    return $user;
}

it('blocks academic pages for a user without academic permissions', function (string $url) {
    test()->actingAs(academicUser());

    test()->get($url)->assertForbidden();
})->with([
    'subjects' => fn () => SubjectResource::getUrl('index'),
    'groups' => fn () => GroupResource::getUrl('index'),
    'classes' => fn () => ClassesResource::getUrl('index'),
    'exam types' => fn () => ExamTypeResource::getUrl('index'),
    'exams' => fn () => ExamResource::getUrl('index'),
    'result rules' => fn () => ResultRuleResource::getUrl('index'),
    'enter marks' => fn () => EnterStudentMarks::getUrl(['examId' => 1, 'subjectId' => 1, 'classId' => 1]),
]);

it('lets view_academic open the lists but not create', function () {
    test()->actingAs(academicUser('view_academic'));

    test()->get(SubjectResource::getUrl('index'))->assertSuccessful();
    test()->get(SubjectResource::getUrl('create'))->assertForbidden();

    Livewire::test(ListSubjects::class)->assertActionHidden(CreateAction::class);
});

it('uses the shared academic permissions for every academic resource', function () {
    test()->actingAs(academicUser('view_academic', 'create_academic'));

    foreach ([SubjectResource::class, GroupResource::class, ClassesResource::class, ExamTypeResource::class, ExamResource::class, ResultRuleResource::class] as $resource) {
        expect($resource::canViewAny())->toBeTrue()
            ->and($resource::canCreate())->toBeTrue();
    }

    expect(EnterStudentMarks::canAccess())->toBeFalse();
});

it('opens marks entry with the academic edit permission', function () {
    test()->actingAs(academicUser('edit_academic'));

    expect(EnterStudentMarks::canAccess())->toBeTrue();
});
