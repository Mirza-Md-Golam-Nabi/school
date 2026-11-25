<?php

use App\Actions\BuildSubjectListPdfAction;
use App\Enums\SubjectListColumn;
use App\Enums\UserType;
use App\Filament\Resources\Subjects\Pages\ListSubjects;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

it('builds a pdf listing every subject with every column selected', function () {
    Subject::create(['name' => 'Bangla', 'name_bn' => 'বাংলা', 'code' => '101', 'has_mcq' => true]);
    Subject::create(['name' => 'Mathematics', 'is_active' => false]);

    expect(app(BuildSubjectListPdfAction::class)->handle())->toStartWith('%PDF')
        ->and(app(BuildSubjectListPdfAction::class)->handle(SubjectListColumn::defaults(), 'L'))->toStartWith('%PDF');
});

it('aborts with 404 when there are no subjects', function () {
    expect(fn () => app(BuildSubjectListPdfAction::class)->handle())
        ->toThrow(NotFoundHttpException::class);
});

it('selects every column by default', function () {
    expect(SubjectListColumn::defaults())->toBe(array_keys(SubjectListColumn::options()));
});

it('renders the selected columns in the subject list view', function () {
    $rendered = view('documents.subject-list', [
        'columns' => collect([SubjectListColumn::Code, SubjectListColumn::Mcq]),
        'rows' => [
            ['serial' => '01', 'name' => 'Physics', 'values' => ['174', 'Yes']],
        ],
    ])->render();

    expect($rendered)
        ->toContain('Subject List')
        ->toContain('Code')
        ->toContain('MCQ')
        ->toContain('Physics')
        ->toContain('174')
        ->not->toContain('Practical');
});

it('streams the subject list pdf as a download for an admin', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    Subject::create(['name' => 'English']);

    $this->actingAs($admin)
        ->get(route('subject-list.download', ['columns' => ['code'], 'orientation' => 'L']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('forbids non-admin users from downloading the subject list', function () {
    $student = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    Subject::create(['name' => 'English']);

    $this->actingAs($student)
        ->get(route('subject-list.download'))
        ->assertForbidden();
});

it('sends the chosen columns and orientation from the download modal to the pdf route', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    Livewire::test(ListSubjects::class)
        ->mountAction('downloadSubjectList')
        ->assertSchemaStateSet(['columns' => SubjectListColumn::defaults(), 'orientation' => 'P'])
        ->set('mountedActions.0.data.columns', ['code', 'has_mcq'])
        ->set('mountedActions.0.data.orientation', 'L')
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertRedirect(route('subject-list.download', [
            'columns' => ['code', 'has_mcq'],
            'orientation' => 'L',
        ]));
});
